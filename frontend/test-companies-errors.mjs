import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  const errors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });

  page.on('pageerror', error => {
    errors.push(`PAGE ERROR: ${error.message}`);
  });

  // Login
  await page.goto('http://localhost:5173/login');
  await page.fill('input[type="email"]', 'admin@techfix.dev');
  await page.fill('input[type="password"]', 'Admin@1234');
  await Promise.all([
    page.waitForNavigation(),
    page.click('button:has-text("Entrar")')
  ]);

  // Acessar empresas com hard refresh
  console.log('Acessando /admin/empresas com Ctrl+Shift+R...');
  await page.goto('http://localhost:5173/admin/empresas', { waitUntil: 'networkidle' });
  await page.keyboard.press('Control+Shift+R');
  await page.waitForLoadState('networkidle');

  console.log('\n=== ERROS ENCONTRADOS ===');
  if (errors.length === 0) {
    console.log('✅ Nenhum erro');
  } else {
    errors.forEach(e => console.log(`❌ ${e}`));
  }

  // Verificar se os componentes estão presentes
  console.log('\n=== VERIFICAÇÃO DE ELEMENTOS ===');
  
  const hasTable = await page.$('table');
  console.log(`Tabela presente: ${hasTable ? '✅' : '❌'}`);

  const headerCells = await page.$$('thead th');
  console.log(`Headers encontrados: ${headerCells.length}`);

  const firstHeader = await page.$eval('thead th:first-child', el => ({
    className: el.className,
    innerHTML: el.innerHTML.substring(0, 50)
  }));
  console.log('Primeiro header:', firstHeader);

  await browser.close();
})().catch(console.error);
