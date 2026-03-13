# N8N Workflow - Errores Corregidos (v3.1.0)

## ❌ Errores Encontrados y Arreglados

### 1. **Nodo 3 (Get History) - URL Endpoint Incorrecto**
**Error:** 
```json
"url": "={{ $env.WORDPRESS_URL }}/wp-json/nexgen/v1/context?offset=0&limit=5"
```

**Problema:** El endpoint `/context` trae contexto empresarial (servicios, precios), no historial de conversación.

**Corrección:**
```json
"url": "={{ $env.WORDPRESS_URL }}/wp-json/nexgen/v1/get_conversation?offset=0&limit=5"
```

**Razón:** El endpoint correcto es `/get_conversation` que devuelve últimos N mensajes del chat.

---

### 2. **Nodo 4 (Build Prompt) - Typo en Cadena**
**Error:**
```javascript
let contextText = history ? `Historial previo:\n${history}\n\nNuevapregunta: ` : '';
```

**Problema:** `Nuevapregunta` sin espacio es un typo. Debería ser `Nueva pregunta`.

**Corrección:**
```javascript
let contextText = history ? `Historial previo:\n${history}\n\nNueva pregunta: ` : 'Nueva pregunta: ';
```

---

### 3. **Nodo 4 (Build Prompt) - Acceso a Datos Incorrecto**
**Error:**
```javascript
message: $input.first().json.message,
session_id: $input.first().json.session_id
```

**Problema:** `$input.first()` en nodo 4 devuelve los datos del nodo 3 (Get History), no los datos originales del Extract Data (nodo 2).

**Corrección:**
```javascript
message: $json.message,
session_id: $json.session_id
```

**Razón:** En nodo 4, `$json` contiene los datos actuales del flujo (message y session_id del nodo 2).

---

### 4. **Nodo 4 (Build Prompt) - Manejo de Historial Incorrecto**
**Error:**
```javascript
if ($json.body && Array.isArray($json.body)) {
  history = $json.body.map(msg => ...
}
```

**Problema:** 
- En nodo 4, `$json` no tiene `.body` (eso es solo para httpRequest responses)
- Necesita acceder a los datos del nodo 3 (Get History) que devuelve array de mensajes

**Corrección:**
```javascript
if ($input.all().length > 0) {
  const historyData = $input.all()[0].json;
  if (Array.isArray(historyData)) {
    history = historyData.map(msg => 
      `${msg.sender || 'Usuario'}: ${msg.text}`
    ).join('\n');
  }
}
```

**Razón:** `$input.all()` accede a TODAS las entradas previas en el flujo, permitiendo obtener el historial del nodo 3.

---

### 5. **Nodo 6 (Parse Response) - Acceso a session_id Perdido**
**Error:**
```javascript
session_id: $input.first().json.session_id
```

**Problema:** 
- En nodo 6, `$input.first()` devuelve datos del nodo 5 (Call Gemini)
- Nodo 5 solo retorna response de Gemini, no tiene session_id
- session_id se perdería y nodo 7 fallaría

**Corrección:**
```javascript
let session_id = '';
let message = '';

// Access all previous inputs to find session_id
if ($input && $input.all && typeof $input.all === 'function') {
  const allItems = $input.all();
  if (allItems && allItems.length > 0) {
    // Find first item with session_id
    for (let item of allItems) {
      if (item.json && item.json.session_id) {
        session_id = item.json.session_id;
        message = item.json.message || '';
        break;
      }
    }
  }
}
```

**Razón:** Busca a través de TODO el historial de inputs para encontrar session_id que fue pasado en nodos anteriores (nodo 2/4).

---

## 📊 Estructura del Flujo Actual (Corregida)

```
Webhook (nodo 1)
    ↓
Extract Data (nodo 2) → message, session_id
    ↓
Get History (nodo 3) → array de mensajes previos
    ↓
Build Prompt (nodo 4) → combina: history + message + session_id
    ↓
Call Gemini (nodo 5) → response de IA
    ↓
Parse Response (nodo 6) → extrae texto + recupera session_id
    ↓
Save to WordPress (nodo 7) → guarda response en BD
    ↓
Response Webhook (nodo 8) → confirma al client
```

---

## ✅ Validación

**Antes de importar en N8N, verifica:**

- [ ] Nodo 3: URL es `/get_conversation` (no `/context`)
- [ ] Nodo 4: `message: $json.message` (no `$input.first()`)
- [ ] Nodo 4: JavaScript usa `$input.all()` para historial
- [ ] Nodo 4: `Nueva pregunta` (con espacio, no typo)
- [ ] Nodo 6: JavaScript busca session_id en todo el flujo
- [ ] Nodo 7: bodyParametersJson referencia `$json.session_id` y `$json.response`

---

## 🔧 Cómo Usar el Workflow Corregido

1. **Importa** `n8n-simple-workflow.json` en tu N8N
2. **Configura variables de entorno:**
   ```
   WORDPRESS_URL=https://tudominio.com
   WORDPRESS_API_KEY=tu-api-key-segura
   GEMINI_API_KEY=tu-gemini-api-key
   ```
3. **Activa el Webhook** (click "Start listening")
4. **Prueba** enviando un mensaje desde el chat widget
5. **Verifica** que:
   - ✓ Historial se carga (últimos 5 mensajes)
   - ✓ Gemini recibe contexto
   - ✓ Respuesta se guarda en WordPress
   - ✓ No hay errores en Executions

---

## 📝 Notas de Implementación

- **Performance:** El Get History es opcional (onError: continueRegularOutput), por lo que si falla no detiene el flujo
- **Context Window:** Solo obtiene últimos 5 mensajes para Gemini (configurable en nodo 3)
- **Security:** Todos los requests usan Bearer token authentication
- **Data Flow:** session_id se mantiene a través de TODO el flujo garantizando que se guarde correctamente

---

**Versión:** 3.1.0  
**Estado:** ✅ Pronto para importar  
**Última actualización:** 13 de Marzo, 2026
