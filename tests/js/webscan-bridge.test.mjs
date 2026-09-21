import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../../public/js/webscan-bridge.js', import.meta.url), 'utf8');

/**
 * Carrega o cliente num contexto isolado, com o `fetch` que o teste quiser.
 * Assim exercitamos o bridge real sem browser e sem agente instalado.
 */
const load = (fetch, options = {}) => {
  const window = { sessionStorage: { getItem: () => '', setItem: () => {}, removeItem: () => {} } };
  const objectUrls = [];
  const context = {
    window, fetch, AbortController, Headers, setTimeout, clearTimeout, atob, Uint8Array, TextDecoder, Blob,
    URL: {
      createObjectURL: (blob) => { const url = `blob:mock/${objectUrls.length}`; objectUrls.push(url); return url; },
      revokeObjectURL: (url) => { const at = objectUrls.indexOf(url); if (at >= 0) objectUrls.splice(at, 1); },
    },
    File: class File {
      constructor(parts, name, opts) {
        this.parts = parts; this.name = name; this.type = opts.type;
        this.size = parts.reduce((total, part) => total + part.length, 0);
      }
    },
  };
  vm.runInNewContext(source, context);
  const bridge = new window.WebScanBridge({ baseUrl: 'http://127.0.0.1:18090', timeoutMs: 40, ...options });
  bridge._objectUrls = objectUrls;
  return bridge;
};

const jsonResponse = (body, ok = true, status = 200) => ({ ok, status, json: async () => body });

/** Router simples: mapeia caminho -> resposta, como faria o agente. */
const routes = (table) => async (url) => {
  const path = url.replace('http://127.0.0.1:18090', '').split('?')[0];
  const handler = table[path];
  if (!handler) throw new TypeError('network');
  return typeof handler === 'function' ? handler() : handler;
};

const base64 = (text) => Buffer.from(text).toString('base64');
const PDF_BYTES = '%PDF-1.4\n1 0 obj\nendobj\ntrailer\n%%EOF\n';

// --- 1. agente offline -------------------------------------------------------

test('reporta agente offline quando a ligação falha', async () => {
  const bridge = load(async () => { throw new TypeError('network'); });
  const status = await bridge.checkStatus();
  assert.equal(status.status, 'offline');
  assert.equal(status.error_code, 'OFFLINE');
  assert.equal(bridge.isOnline, false);
});

test('reporta pareamento em falta com o código que o modal usa', async () => {
  const bridge = load(routes({
    '/status': jsonResponse({ error_code: 'PAIRING_REQUIRED', message: 'Introduza o código.' }, false, 401),
  }));
  const status = await bridge.checkStatus();
  assert.equal(status.status, 'offline');
  assert.equal(status.error_code, 'PAIRING_REQUIRED');
});

// --- 2. scanner detetado -----------------------------------------------------

test('lista os scanners detetados com as capacidades', async () => {
  const bridge = load(routes({
    '/status': jsonResponse({ status: 'online', version: '1.0.0' }),
    '/scanners': jsonResponse({
      status: 'success',
      scanners: [{
        id: 'wia_hp', name: 'HP ScanJet', driver: 'WIA', is_default: true,
        capabilities: { sources: ['adf', 'flatbed'], color_modes: ['gray', 'color'], dpis: [200, 300], duplex_supported: true },
      }],
    }),
  }));
  const scanners = await bridge.getScanners();
  assert.equal(scanners.length, 1);
  assert.equal(scanners[0].id, 'wia_hp');
  assert.equal(scanners[0].capabilities.duplex_supported, true);
  assert.deepEqual(scanners[0].capabilities.dpis, [200, 300]);
});

test('envia o token de pareamento nos cabeçalhos', async () => {
  let seen = null;
  const bridge = load(async (url, opts) => { seen = opts.headers; return jsonResponse({ status: 'online' }); });
  bridge.pairingToken = 'segredo-local';
  await bridge.checkStatus();
  assert.equal(seen.get('X-WebScan-Pairing'), 'segredo-local');
});

// --- 3. lista vazia ----------------------------------------------------------

test('devolve lista vazia quando não há scanner ligado', async () => {
  const bridge = load(routes({
    '/status': jsonResponse({ status: 'online' }),
    '/scanners': jsonResponse({ status: 'success', scanners: [] }),
  }));
  assert.equal((await bridge.getScanners()).length, 0);
});

test('não consulta scanners quando o agente está offline', async () => {
  let scannersCalled = false;
  const bridge = load(async (url) => {
    if (url.endsWith('/scanners')) { scannersCalled = true; }
    if (url.endsWith('/status')) return jsonResponse({ status: 'offline' });
    return jsonResponse({ scanners: [] });
  });
  // O array vem do realm do vm, por isso comparamos o comprimento e não a referência.
  assert.equal((await bridge.getScanners()).length, 0);
  assert.equal(scannersCalled, false);
});

// --- 4. erro de hardware -----------------------------------------------------

test('propaga erro estruturado de hardware com o código do agente', async () => {
  const bridge = load(routes({
    '/scan': jsonResponse({ status: 'error', error_code: 'ADF_EMPTY', message: 'O alimentador está vazio.' }, false, 409),
  }));
  await assert.rejects(
    () => bridge.startScan({ scanner_id: 'wia_hp' }),
    (error) => {
      assert.equal(error.code, 'ADF_EMPTY');
      assert.equal(error.message, 'O alimentador está vazio.');
      return true;
    },
  );
});

test('exige um scanner selecionado antes de digitalizar', async () => {
  const bridge = load(async () => jsonResponse({}));
  await assert.rejects(() => bridge.startScan({}), /Selecione um scanner/);
});

// --- 5. timeout --------------------------------------------------------------

test('converte um pedido pendurado em erro de tempo limite', async () => {
  const bridge = load((url, opts) => new Promise((_, reject) => {
    opts.signal.addEventListener('abort', () => {
      const error = new Error('aborted');
      error.name = 'AbortError';
      reject(error);
    });
  }));
  const status = await bridge.checkStatus();
  assert.equal(status.error_code, 'TIMEOUT');
});

// --- 6. retorno de PDF válido ------------------------------------------------

test('aceita um PDF real devolvido pelo agente', () => {
  const bridge = load();
  const pdf = bridge.pdfFileFromResponse({ pdf_base64: base64(PDF_BYTES), filename: 'scan.pdf' });
  assert.equal(pdf.type, 'application/pdf');
  assert.equal(pdf.name, 'scan.pdf');
  assert.ok(pdf.size > 0);
});

test('digitalização completa guarda o PDF e as miniaturas', async () => {
  const bridge = load(routes({
    '/scan': jsonResponse({
      status: 'success', page_count: 2, filename: 'digitalizacao.pdf', pdf_base64: base64(PDF_BYTES),
      pages: [
        { page_number: 1, mime_type: 'image/jpeg', preview_base64: base64('fake-jpeg-1') },
        { page_number: 2, mime_type: 'image/jpeg', preview_base64: base64('fake-jpeg-2') },
      ],
    }),
    '/progress': jsonResponse({ status: 'success', active: false, percent: 0 }),
  }));

  const progresso = [];
  bridge.onProgressCallback = (event) => progresso.push(event.percent);

  const result = await bridge.startScan({ scanner_id: 'wia_hp', dpi: 200 });
  assert.equal(result.file.type, 'application/pdf');
  assert.equal(bridge.pages.length, 2);
  assert.equal(bridge.pages[0].number, 1);
  assert.ok(bridge.pages[0].previewUrl.startsWith('blob:'));
  assert.equal(progresso.at(-1), 100);
});

test('clear liberta as URLs das miniaturas', async () => {
  const bridge = load(routes({
    '/scan': jsonResponse({
      status: 'success', pdf_base64: base64(PDF_BYTES),
      pages: [{ page_number: 1, preview_base64: base64('fake') }],
    }),
    '/progress': jsonResponse({ active: false }),
  }));
  await bridge.startScan({ scanner_id: 'wia_hp' });
  assert.equal(bridge._objectUrls.length, 1);
  bridge.clear();
  assert.equal(bridge._objectUrls.length, 0);
  assert.equal(bridge.lastPdf, null);
});

// --- 7. retorno inválido -----------------------------------------------------

test('recusa conteúdo que não é PDF, mesmo com nome .pdf', () => {
  const bridge = load();
  assert.throws(
    () => bridge.pdfFileFromResponse({ pdf_base64: Buffer.from([0xff, 0xd8, 0xff, 0xe0]).toString('base64'), filename: 'x.pdf' }),
    (error) => { assert.equal(error.code, 'PDF_INVALID'); return true; },
  );
});

test('recusa resposta sem PDF nenhum', () => {
  const bridge = load();
  assert.throws(
    () => bridge.pdfFileFromResponse({ status: 'success', pages: [] }),
    (error) => { assert.equal(error.code, 'PDF_MISSING'); return true; },
  );
});

test('recusa base64 corrompido', () => {
  const bridge = load();
  assert.throws(
    () => bridge.pdfFileFromResponse({ pdf_base64: '!!!nao-e-base64!!!' }),
    (error) => { assert.ok(['PDF_INVALID', 'PDF_MISSING'].includes(error.code)); return true; },
  );
});

test('ignora miniaturas corrompidas sem perder o PDF', async () => {
  const bridge = load(routes({
    '/scan': jsonResponse({
      status: 'success', pdf_base64: base64(PDF_BYTES),
      pages: [
        { page_number: 1, preview_base64: '!!!lixo!!!' },
        { page_number: 2, preview_base64: base64('fake-jpeg') },
      ],
    }),
    '/progress': jsonResponse({ active: false }),
  }));
  const result = await bridge.startScan({ scanner_id: 'wia_hp' });
  assert.equal(result.file.type, 'application/pdf');
  assert.equal(bridge.pages.length, 1, 'só a miniatura legível é mantida');
});
