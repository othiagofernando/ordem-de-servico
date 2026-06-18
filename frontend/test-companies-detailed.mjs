import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  const errors = [];
  page.on('console', msg => {
    if (msg.type() === 'error' || msg.type() === 'warning') {
      errors.push(`[${msg.type()}] ${msg.text()}`);
    }
  });

  // Login
  await page.goto('http://localhost:5173/login');
  await page.fill('input[type="email"]', 'admin@techfix.dev');
  await page.fill('input[type="password"]', 'Admin@1234');
  await Promise.all([
    page.waitForNavigation(),
    page.click('button:has-text("Entrar")')
  ]);

  // Acessar empresas
  console.log('Acessando /admin/empresas...');
  await page.goto('http://localhost:5173/admin/empresas');
  await page.waitForLoadState('networkidle');
  
  console.log('\n=== DIAGNOSTICO ===\n');

  // Ver o conteúdo principal
  const mainContent = await page.$('main');
  if (mainContent) {
    const text = await mainContent.textContent();
    console.log('Conteúdo main:', text?.substring(0, 200));
  } else {
    console.log('❌ Main não encontrado');
  }

  // Ver se a tabela está em carregamento
  const loadingDiv = await page.$('div:has-text("Carregando empresas")');
  if (loadingDiv) {
    console.log('⏳ Página está em estado de carregamento!');
  }

  // Ver se há tabela
  const table = await page.$('table');
  if (table) {
    const rows = await page.$$('tbody tr');
    console.log(`✅ Tabela encontrada com ${rows.length} linhas`);
  } else {
    console.log('❌ Tabela não encontrada');
  }

  // Ver erros
  if (errors.length > 0) {
    console.log('\nErros/Warnings:');
    errors.slice(0, 5).forEach(e => console.log(`  ${e}`));
  }

  await browser.close();
})().catch(console.error);
