import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Login
  console.log('1️⃣ Fazendo login como super_admin...');
  await page.goto('http://localhost:5173/login');
  await page.fill('input[type="email"]', 'admin@techfix.dev');
  await page.fill('input[type="password"]', 'Admin@1234');
  
  const navPromise = page.waitForNavigation();
  await page.click('button:has-text("Entrar")');
  await navPromise;
  
  const urlAfterLogin = page.url();
  console.log('URL após login:', urlAfterLogin);

  // Acessar admin/empresas diretamente
  console.log('\n2️⃣ Acessando /admin/empresas...');
  await page.goto('http://localhost:5173/admin/empresas', { waitUntil: 'networkidle' });
  
  const currentUrl = page.url();
  console.log('URL final:', currentUrl);

  if (currentUrl.includes('/admin/empresas')) {
    console.log('✅ Estamos na página de empresas');
    
    // Aguardar tabela
    const table = await page.waitForSelector('table', { timeout: 3000 }).catch(() => null);
    if (table) {
      const headers = await page.$$eval('thead th', ths => ths.map(th => th.textContent.trim()));
      console.log('Headers encontrados:', headers);
    } else {
      console.log('❌ Tabela não encontrada mesmo após aguardar');
    }
  } else {
    console.log('❌ Redirecionado para:', currentUrl);
  }

  await browser.close();
})().catch(console.error);
