
$htmlPath = "c:\Users\isaac\OneDrive\Documentos\GitHub\GPN-AGIL\proposta_temp.html"
$docxPath = "c:\Users\isaac\OneDrive\Documentos\GitHub\GPN-AGIL\PROPOSTA_COMERCIAL_FINAL.docx"

Write-Host "Iniciando conversão para Word..."

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = "wdAlertsNone"

    $doc = $word.Documents.Open($htmlPath)
    
    # Save as DOCX (16 = wdFormatDocumentDefault)
    $doc.SaveAs([ref]$docxPath, [ref]16)
    
    $doc.Close()
    $word.Quit()
    
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null
    Remove-Item $htmlPath # Clean up temp file
    
    Write-Host "Sucesso! Arquivo criado em: $docxPath"
} catch {
    Write-Error "Erro ao criar arquivo Word. Verifique se o Microsoft Word está instalado."
    Write-Error $_.Exception.Message
}
