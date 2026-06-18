import { test, expect, Browser, BrowserContext, Page } from '@playwright/test'

let browser: Browser
let context: BrowserContext
let page: Page

test.describe('Máscaras e CEP Lookup', () => {
  test.beforeAll(async () => {
    const { chromium } = await import('@playwright/test')
    browser = await chromium.launch()
  })

  test.beforeEach(async () => {
    context = await browser.createIncognitoBrowser()
    page = await context.newPage()
    
    const adminToken = 'dev-admin-token-aaaabbbbccccddddeeeeffffgggghhhhiiiijjjjkkkkllll'
    await page.evaluate((t) => localStorage.setItem('access_token', t), adminToken)
  })

  test('Máscara de Telefone no Formulário de Empresa', async () => {
    await page.goto('http://localhost:5173/admin/companies/new')
    
    const phoneInput = page.locator('input[placeholder="(48) 3524 0388"]')
    await phoneInput.fill('4835240388')
    
    const value = await phoneInput.inputValue()
    expect(value).toBe('(48) 3524 0388')
  })

  test('Máscara de CNPJ no Formulário de Empresa', async () => {
    await page.goto('http://localhost:5173/admin/companies/new')
    
    const docInput = page.locator('input[maxlength="18"]').first()
    await docInput.fill('11222333000181')
    
    const value = await docInput.inputValue()
    expect(value).toBe('11.222.333/0001-81')
  })

  test('Máscara de CEP e Lookup', async () => {
    await page.goto('http://localhost:5173/admin/companies/new')
    
    const cepInput = page.locator('input[placeholder="88901-056"]')
    await cepInput.fill('88901056')
    
    const value = await cepInput.inputValue()
    expect(value).toBe('88901-056')
    
    // Aguardar o lookup ser executado
    await page.waitForTimeout(1000)
    
    const addressInput = page.locator('input[placeholder*="Endereço"]')
    const addressValue = await addressInput.inputValue()
    expect(addressValue).toContain('Presidente')
  })

  test.afterEach(async () => {
    await context.close()
  })

  test.afterAll(async () => {
    await browser.close()
  })
})
