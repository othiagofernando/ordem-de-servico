import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  console.log('\n=== TESTE: TABELA DE EMPRESAS COM ORDENAÇÃO E PAGINAÇÃO ===\n');

  // Login
  console.log('1️⃣ Fazendo login...');
  await page.goto('http://localhost:5173/login');
  await page.fill('input[type="email"]', 'admin@techfix.dev');
  await page.fill('input[type="password"]', 'Admin@1234');
  await Promise.all([
    page.waitForNavigation(),
    page.click('button:has-text("Entrar")')
  ]);

  // Acessar empresas
  console.log('2️⃣ Acessando /admin/empresas...');
  await page.goto('http://localhost:5173/admin/empresas');
  await page.waitForLoadState('networkidle');

  // Verificar headers com ordenação
  console.log('\n3️⃣ Verificando headers clicáveis...');
  const headers = await page.$$eval('th.th-sort', headers =>
    headers.map(h => h.textContent.trim())
  );
  console.log('Headers com ordenação:', headers);

  if (headers.length > 0) {
    console.log('✅ Headers com ThSort encontrados!');
  } else {
    console.log('❌ Nenhum header com ThSort encontrado!');
  }

  // Verificar paginação
  console.log('\n4️⃣ Verificando paginação...');
  const pagination = await page.$('.pagination-bar');
  if (pagination) {
    console.log('✅ Barra de paginação encontrada!');
    
    const paginationText = await page.$eval('.pagination-bar', el => el.textContent);
    console.log('Texto:', paginationText.substring(0, 100));
  } else {
    console.log('⚠️ Barra de paginação não encontrada');
  }

  // Testar ordenação
  console.log('\n5️⃣ Testando clique em header de ordenação...');
  const nameHeader = await page.$('th.th-sort:has-text("Empresa")');
  if (nameHeader) {
    const firstRowBefore = await page.$eval('tbody tr:first-child td:first-child', el => el.textContent);
    console.log('Primeira linha antes:', firstRowBefore.trim().substring(0, 30));

    await nameHeader.click();
    await page.waitForTimeout(500);

    const firstRowAfter = await page.$eval('tbody tr:first-child td:first-child', el => el.textContent);
    console.log('Primeira linha depois:', firstRowAfter.trim().substring(0, 30));

    if (firstRowBefore !== firstRowAfter) {
      console.log('✅ Ordenação funcionando!');
    } else {
      console.log('⚠️ Ordem não mudou (pode ter apenas uma empresa)');
    }
  }

  console.log('\n=== TESTE CONCLUÍDO ===\n');
  await browser.close();
})().catch(console.error);
