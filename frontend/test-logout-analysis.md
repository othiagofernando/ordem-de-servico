# Análise do Comportamento de Logout

## Fluxo esperado:
1. Usuário clica em "Sair da conta" (botão no AppLayout.vue)
2. Função logout() é acionada:
   - Chama await auth.logout()
   - Tenta await router.push(tenantLoginRoute() || { name: 'login' })
3. auth.logout() (stores/auth.ts):
   - Faz POST em /auth/logout (ignora erros)
   - Limpa token e context
   - Remove token do localStorage
4. tenantLoginRoute() (composables/useTenantRouting.ts):
   - Se auth.isAuthenticated && tenantSlug.value retorna tenant-login
   - Senão retorna { name: 'login' }
   - Como auth.isAuthenticated é false após logout, retorna { name: 'login' }
5. router.push({ name: 'login' }) navegará para /login
6. O beforeEach guard verifica:
   - Se routertem meta.guest=true e auth.isAuthenticated=false, permite
   - /login tem meta.guest=true, então é permitido

## Possíveis problemas:

### Problema 1: router.push não está realmente navegando
- Sintoma: página não muda após logout
- Verificar: há um catch silencioso em AppLayout.vue linha 23?

### Problema 2: O guard está redirecionando de volta
- O guard em router.beforeEach(linha 412-414) redireciona se meta.guest && auth.isAuthenticated
- Mas como auth.isAuthenticated é false após logout, isso não deve acontecer
- Porém, talvez haja uma race condition

### Problema 3: O tenantLoginRoute() está retornando algo errado
- Se tenantSlug.value ainda tiver um valor após logout
- Então retornaria { name: 'tenant-login', params: { tenantSlug: ... } }
- E isso poderia causar um redirect inesperado

## Recomendação para testar:
Verificar console.log em AppLayout.vue logo após o logout para ver se:
- router.push foi acionado
- Qual URL ele está tentando navegar
- Se há erros no console
