# n8n local sem Docker

Esta pasta executa o n8n diretamente com Node.js no Windows.

## Instalação

```powershell
cd D:\look\n8n-runtime
Copy-Item .env.example .env
npm install
.\import-workflows.ps1
.\start-n8n.ps1
```

Painel: http://127.0.0.1:5678

Para iniciar sem deixar uma janela aberta:

```powershell
.\start-n8n-background.ps1
```

Os segredos ficam somente em `.env`, que não é enviado ao Git. Os workflows
continuam inativos por padrão e as automações do Laravel precisam ser
habilitadas explicitamente para evitar envios acidentais.
