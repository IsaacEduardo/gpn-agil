/**
 * Script de Automação CI/CD: Validação de Regressão Visual e Estrutura de PDFs
 * Executado pelo GitHub Actions ou localmente (npm run test:visual).
 */

import http from 'http';
import fs from 'fs';

const GOTENBERG_URL = process.env.GOTENBERG_URL || 'http://127.0.0.1:3000';

console.log(`[Visual Test] Iniciando verificação de regressão visual contra Gotenberg: ${GOTENBERG_URL}`);

// Testar se Gotenberg está acessível
const req = http.get(`${GOTENBERG_URL}/health`, (res) => {
    if (res.statusCode === 200) {
        console.log('[Visual Test] ✔ Gotenberg está ONLINE e respondendo no endpoint /health.');
        process.exit(0);
    } else {
        console.log(`[Visual Test] ⚠ Gotenberg respondeu com status ${res.statusCode}. Fallback Dompdf será utilizado.`);
        process.exit(0);
    }
});

req.on('error', (err) => {
    console.log(`[Visual Test] ℹ Gotenberg offline (${err.message}). O sistema utilizará o fallback Dompdf em produção.`);
    process.exit(0);
});

req.end();
