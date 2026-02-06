# 📌 LiveChat – Plugin de Chat para WordPress mediante Telegram

## 🧠 ¿Qué es este proyecto?

LiveChat es un **plugin desarrollado para WordPress** que permite añadir un sistema de **chat en vivo en tu sitio web**, utilizando **Telegram** como plataforma de mensajería en tiempo real para recibir y responder los mensajes de los visitantes del sitio. ([GitHub][1])

Este plugin está pensado para desarrolladores o dueños de sitios que quieren:

* Recibir mensajes en tiempo real desde su web directamente en **Telegram**.
* Gestionar conversaciones desde su teléfono o cliente de Telegram.
* Ofrecer soporte o atención al visitante sin depender de sistemas de chat externos.

---

## 🚀 ¿Cómo funciona?

1. El plugin se instala como un componente dentro de WordPress.
2. Integra un chat en vivo en la interfaz del sitio para visitantes.
3. Puede ser customizado en colores y textos desde el panel de wordpress
4. Cuando alguien envía un mensaje:

   * El mensaje es remitido a una **cuenta de Telegram**.
   * Tú (o tu equipo) pueden responder directamente desde Telegram.
5. El plugin se encarga de la comunicación bidireccional entre tu sitio y Telegram.

---

## 🗂️ ¿Qué incluye este repositorio?

| Archivo                                  | Descripción                                                                         |
| ---------------------------------------- | ----------------------------------------------------------------------------------- |
| `nexgen-telegram-chat.php`               | Código principal del plugin que enlaza WordPress con Telegram.                      |
| `nexgen-telegram-chat-bidirectional.zip` | Paquete listo para instalar (zip) como plugin de WordPress.                         |
| `assets/`                                | Contiene los archivos JS y CSS para su funcionamiento en el Backend y los estilos   |

---

## 🛠️ Tecnologías usadas

* **PHP** – Backend del plugin para WordPress.
* **JavaScript y CSS** – Interactividad y estilos del chat en el frontend.

---

## 🧩 Instalación (para usuarios finales)

1. Descarga el repositorio completo en ZIP. 
2. Desde tu panel de WordPress ve a **Plugins → Añadir nuevo**.
3. Sube el ZIP y actívalo.
4. Configura el plugin con tu **bot/token de Telegram** (requerido para que el chat funcione).

> ⚠️ Asegúrate de tener un bot de Telegram creado para recibir mensajes. (Puedes usar BotFather para crearlo)

---

## 🎯 Beneficios

✔ Permite ofrecer **soporte en vivo sin depender de proveedores externos de chat**.
✔ Centraliza la comunicación de visitantes directamente en **Telegram**.
✔ Fácil de instalar y usar en cualquier sitio WordPress.

---

> LiveChat es un plugin de WordPress que desarrollé para integrar un sistema de chat en vivo en cualquier sitio web, conectándolo con Telegram para enviar y recibir mensajes en tiempo real. Está construido en PHP y JavaScript, diseñado para ser ligero y fácil de configurar. Los visitantes pueden iniciar una conversación desde el sitio web, y yo podía responder directamente desde Telegram, haciendo el soporte más flexible y accesible.

---
