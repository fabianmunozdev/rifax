# Arquitectura

## Vista General
```text
Comprador -> WhatsApp Cloud API -> Webhook Laravel
                                  |-> Motor de Conversacion
                                  |-> Logica de Compra
                                  |-> PostgreSQL
                                  |-> Redis / Queues / Scheduler
                                  |-> Filament Admin
                                  |-> Generador de Boletos
                                  |-> Modulo de Campanas
                                  |-> Modulo de IA / FAQ
```

## Stack
- Laravel 12.
- PostgreSQL.
- Redis.
- Laravel Queues y Scheduler.
- FilamentPHP.
- WhatsApp Cloud API.
- Intervention Image o libreria equivalente para imagenes.
- OpenAI solo para FAQ y soporte conversacional.
- Docker Compose recomendado para desarrollo local.

## Componentes
### Webhook de WhatsApp
Recibe mensajes entrantes, valida firma, normaliza payloads y dispara el flujo correcto segun el estado conversacional y el tipo de mensaje.

### Motor de Conversacion
Controla menus, contexto del usuario, validaciones, mensajes esperados y reglas de fallback. Debe ser determinista para el flujo transaccional.

### Dominio de Compra
Gestiona rifas, disponibilidad, reservas, compras, pagos, asignacion de numeros y generacion de boletos.

### Panel Administrativo
Permite operar el negocio: crear rifas, revisar pagos, ver estados, gestionar branding, configurar mensajes y ejecutar acciones manuales.

### Generador de Boletos
Produce imagen final, miniatura, URL publica verificable y trazabilidad del boleto emitido.

### Campanas
Programa y envia recordatorios o mensajes operativos mediante colas. Debe soportar segmentacion y control de duplicados.

### IA / FAQ
Responde preguntas abiertas usando una base de conocimiento controlada. Nunca debe cambiar estados de compra, pago o boletos.

## Principios Tecnicos
- Servicios y acciones por caso de uso, evitando controladores con demasiada logica.
- Jobs para tareas lentas o asincronas.
- Eventos de dominio para acciones derivadas como generacion de boleto o notificaciones.
- Idempotencia en webhooks y acciones sensibles.
- Auditoria en acciones administrativas criticas.
- Logging estructurado y trazabilidad por compra, rifa y mensaje.
- Para cualquier tooling o dependencia Node del proyecto, usar `pnpm` como gestor de paquetes.

## Configuracion de Empresa
Aunque el sistema no es multiempresa, debe existir una capa de configuracion central para:
- Nombre comercial.
- Logo y assets.
- Colores y branding.
- Datos legales.
- Numeros de WhatsApp.
- Metodos de pago.
- Textos de ayuda, condiciones y FAQs.

## Evolucion Futura
La arquitectura debe dejar preparado el dominio para introducir una entidad `company` o `tenant` a futuro, pero sin contaminar el MVP con complejidad de aislamiento de datos o administracion multiempresa.
