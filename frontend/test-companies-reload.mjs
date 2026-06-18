import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Login
  console.log('Fazendo login...');
  await page.goto('http://localhost:5173/login');
  await page.fill('input[type="email"]', 'admin@techfix.dev');
  await page.fill('input[type="password"]', 'Admin@1234');
  await Promise.all([
    page.waitForNavigation(),
    page.click('button:has-text("Entrar")')
  ]);

  // Acessar empresas
  console.log('Acessando /admin/empresas...');
  await page.goto('http://localhost:5173/admin/empresas', { waitUntil: 'networkidle' });
  
  // Forçar reload
  console.log('Recarregando página...');
  await page.reload({ waitUntil: 'networkidle' });
  
  await page.waitForTimeout(2000); // Esperar mais tempo

  // Verificar
  console.log('\nVerificando página...');
  const html = await page.content();
  
  if (html.includes('ThSort')) {
    console.log('✅ ThSort encontrado no HTML');
  } else {
    console.log('❌ ThSort NÃO encontrado no HTML');
  }

  if (html.includes('AppPagination')) {
    console.log('✅ AppPagination encontrado no HTML');
  } else {
    console.log('❌ AppPagination NÃO encontrado no HTML');
  }

  if (html.includes('th-sort')) {
    console.log('✅ Classe th-sort encontrada no HTML');
  } else {
    console.log('❌ Classe th-sort NÃO encontrada no HTML');
  }

  await browser.close();
})().catch(console.error);
