# Padrão de Tratamento de Erros no Frontend

## Problema Original
Quando um usuário tentava acessar um recurso sem permissão (erro 403), recebia a mensagem genérica **"Erro ao carregar dados..."** que parecia ser um erro de sistema, não uma falta de permissão.

## Solução Implementada

### 1. Classe `ApiError` Melhorada (`api/client.ts`)
- Adiciona propriedade `isForbidden` para detectar erros 403
- Mensagens padrão diferenciadas por tipo de erro:
  - **403**: "Você não tem permissão para acessar este recurso"
  - **401**: "Sua sessão expirou. Faça login novamente"
  - **404**: "Recurso não encontrado"
  - **422**: "Dados inválidos"
  - **5xx**: "Erro no servidor. Tente novamente mais tarde"

### 2. Composable `useApiError` (`composables/useApiError.ts`)
Facilita o tratamento consistente de erros em componentes:

```typescript
const { error, handleError } = useApiError()

onMounted(async () => {
  try {
    const data = await api.list()
  } catch (e) {
    handleError(e)  // Detecta e estrutura o erro
  }
})
```

### 3. Componente `ApiErrorAlert.vue` (`components/ApiErrorAlert.vue`)
Renderiza erros de forma consistente com:
- Cores diferentes para permissão (amber) vs erro (red)
- Ícones de contraste: 🔒 para permissão, ⚠️ para erro
- Estrutura legível

## Como Aplicar em Outras Views

### Passo 1: Importar Composable
```typescript
import { useApiError } from '../../composables/useApiError'
```

### Passo 2: Substituir `error` ref
**Antes:**
```typescript
const error = ref('')
```

**Depois:**
```typescript
const { error, handleError } = useApiError()
```

### Passo 3: Atualizar onMounted
**Antes:**
```typescript
} catch {
  error.value = 'Erro ao carregar dados de X.'
}
```

**Depois:**
```typescript
} catch (e) {
  handleError(e)
}
```

### Passo 4: Importar e Usar Componente
**Adicione no script:**
```typescript
import ApiErrorAlert from '../../components/ApiErrorAlert.vue'
```

**Substitua na template:**
```html
<!-- Antes -->
<div v-if="error" class="rounded-2xl border border-red-500/20 ...">
  {{ error }}
</div>

<!-- Depois -->
<ApiErrorAlert :error="error" />
```

## Views Já Atualizadas ✅
- `AdminPermissionsView.vue`
- `AdminUsersView.vue`

## Views Pendentes ⏳
- `AdminAuditView.vue`
- `AdminAlertsView.vue`
- `AdminBillingDetailView.vue`
- `AdminCompanyAuditView.vue`
- ... (20+ views no total)

## Caso de Uso: Usuário com Perfil Restrito

Antes:
```
Erro ao carregar dados de permissões.
```
(Parece um erro de servidor)

Depois:
```
🔒 Acesso negado
Você não tem permissão para acessar este recurso
```
(Mensagem clara sobre a restrição)

## Benefícios
1. **UX Melhorado**: Diferencia erro de sistema vs falta de permissão
2. **Consistência**: Padrão reutilizável em todo o frontend
3. **Acessibilidade**: Cores e ícones ajudam na identificação
4. **Manutenibilidade**: Mensagens centralizadas em `client.ts`
