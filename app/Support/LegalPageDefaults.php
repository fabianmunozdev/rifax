<?php

namespace App\Support;

use App\Models\LegalPage;

/**
 * Textos iniciales de las páginas legales. Se cargan una sola vez; después se editan en el panel.
 * Están escritos con base en la Ley 643 de 2001, el Decreto 1068 de 2015 (modificado por el
 * Decreto 1486 de 2024), la Ley 1480 de 2011, la Ley 527 de 1999, la Ley 1581 de 2012 y el
 * Estatuto Tributario. Deben ser revisados por un abogado antes de usarse en producción.
 */
class LegalPageDefaults
{
    /**
     * @return list<array{slug: string, title: string, summary: string, body: string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => LegalPage::TERMS,
                'title' => 'Términos y condiciones',
                'summary' => 'Reglas para participar en las rifas de {empresa}: compra, pago, sorteo y entrega de premios.',
                'body' => self::terms(),
            ],
            [
                'slug' => LegalPage::PRIVACY,
                'title' => 'Política de tratamiento de datos personales',
                'summary' => 'Cómo {empresa} recoge, usa y protege tus datos personales, y cómo puedes ejercer tus derechos.',
                'body' => self::privacy(),
            ],
        ];
    }

    protected static function terms(): string
    {
        return <<<'HTML'
<h2>1. Quiénes somos</h2>
<p>Este sitio y el canal de WhatsApp asociado son operados por <strong>{razon_social}</strong>, identificada con NIT <strong>{nit}</strong>, bajo la marca <strong>{empresa}</strong> (en adelante, "nosotros"). Puedes contactarnos en {correo}, al teléfono {telefono} o por WhatsApp al {whatsapp}.</p>
<p>Al apartar o comprar un número aceptas estos términos. Si no estás de acuerdo con ellos, no participes.</p>

<h2>2. Marco legal</h2>
<p>En Colombia las rifas son un juego de suerte y azar y hacen parte del monopolio rentístico del Estado (artículo 336 de la Constitución, Ley 643 de 2001 y Decreto 1068 de 2015, modificado por el Decreto 1486 de 2024). Por eso:</p>
<ul>
<li>Cada rifa se realiza solo con autorización previa de la autoridad competente: Coljuegos cuando la rifa se vende en varios departamentos o en todo el país, o la autoridad del departamento, distrito o municipio cuando se vende solo allí.</li>
<li>En la página de cada rifa publicamos el número y la fecha del acto administrativo que la autoriza, la lotería o el mecanismo con el que se juega, la fecha del sorteo y el plan de premios.</li>
<li>Los premios son bienes nuevos, de nuestra propiedad, y no se entregan en dinero en efectivo.</li>
<li>El cumplimiento del plan de premios está respaldado por una garantía a favor de la autoridad que autoriza la rifa.</li>
</ul>
<p>Si alguna rifa aparece publicada sin esta información, no compres y avísanos.</p>

<h2>3. Quién puede participar</h2>
<ul>
<li>Personas mayores de 18 años, con documento de identidad válido. Está prohibida la venta a menores de edad (artículo 4 de la Ley 643 de 2001).</li>
<li>Para reclamar un premio el ganador debe demostrar su identidad, ser la misma persona registrada en la compra y ser mayor de edad.</li>
<li>Podemos rechazar o anular compras hechas con datos falsos, de terceros sin autorización o con medios de pago que no pertenecen al comprador.</li>
</ul>

<h2>4. Cómo funciona la compra</h2>
<ol>
<li><strong>Apartar números.</strong> Eliges tus números en la página de la rifa o por WhatsApp. Los números quedan apartados a tu nombre por el tiempo indicado en la rifa. Si no envías el pago dentro de ese plazo, se liberan y otra persona puede tomarlos.</li>
<li><strong>Pagar.</strong> Pagas por transferencia o consignación a las cuentas que aparecen en el sitio o que te indica el bot. Nunca te pediremos pagar a cuentas personales distintas a las publicadas, ni claves, ni códigos de verificación.</li>
<li><strong>Enviar el comprobante.</strong> Envías la foto del comprobante por WhatsApp. Nuestro equipo lo revisa contra el movimiento real de la cuenta.</li>
<li><strong>Recibir la boleta.</strong> Cuando el pago se aprueba, te enviamos tu boleta digital con un código y un código QR que cualquiera puede verificar en este sitio. Solo una boleta aprobada participa en el sorteo.</li>
</ol>
<p>Cada rifa puede tener un mínimo y un máximo de números por persona. Esos límites se muestran en la rifa.</p>
<p>Las ventas se hacen a través de una plataforma en línea que registra cada boleta en tiempo real. No se venden boletas con series ni fraccionadas.</p>

<h2>5. Precio, pagos y devoluciones</h2>
<ul>
<li>El precio de cada número es el publicado en la rifa, en pesos colombianos e incluye los impuestos que correspondan.</li>
<li>Al tratarse de un juego de suerte y azar con fecha cierta de sorteo, no aplica el derecho de retracto de las ventas a distancia (artículo 47 de la Ley 1480 de 2011, excepción para servicios cuya ejecución empieza con el acuerdo del consumidor y bienes de prestación inmediata).</li>
<li>Si un pago no se puede verificar, se rechaza y te avisamos por WhatsApp para que lo corrijas.</li>
<li>Si pagaste de más, pagaste dos veces o la rifa se cancela, te devolvemos el dinero a la misma cuenta de origen dentro de los quince (15) días hábiles siguientes a que lo solicites o a que anunciemos la cancelación.</li>
</ul>

<h2>6. Sorteo y resultados</h2>
<ul>
<li>El número ganador sale del resultado oficial de la lotería indicada en la rifa (por ejemplo, las últimas cifras del premio mayor), en la fecha y hora publicadas, o del generador de números aleatorios aprobado por la autoridad cuando así se indique.</li>
<li>Nosotros no elegimos ni podemos influir en el número ganador.</li>
<li>Publicamos el resultado en este sitio y lo comunicamos al ganador por WhatsApp.</li>
<li>Algunas rifas tienen premios anticipados con su propia fecha y lotería. Sus reglas aparecen en la página de la rifa y se rigen por estos mismos términos.</li>
</ul>

<h2>7. Si el número ganador no se vendió o el sorteo cambia</h2>
<p>El premio debe quedar siempre en poder del público. Si el número ganador no fue vendido, o si por fuerza mayor el sorteo no se puede hacer en la fecha prevista, informaremos a la autoridad que autorizó la rifa, fijaremos una nueva fecha según sus instrucciones y lo avisaremos a todos los participantes por los mismos canales en los que vendimos (este sitio y WhatsApp). Tu boleta sigue siendo válida para el nuevo sorteo.</p>

<h2>8. Entrega de premios</h2>
<ul>
<li>Como la venta es en línea, la boleta es nominativa: el premio se entrega únicamente a la persona registrada en la compra, previa verificación de su identidad.</li>
<li>El ganador tiene el plazo indicado en la boleta para reclamar el premio. Pasado ese plazo, el premio caduca conforme a la ley.</li>
<li>Los premios se entregan tal como se describen en la rifa. No se cambian por dinero ni por otros bienes.</li>
<li>Los gastos de traspaso, matrícula, SOAT u otros trámites se rigen por lo que diga cada rifa. Si no dice nada, corren por cuenta del ganador.</li>
<li>Después de la entrega presentamos ante la autoridad la declaración que exige la norma, y podemos publicar el nombre parcial del ganador y fotos de la entrega solo con su autorización.</li>
</ul>

<h2>9. Impuestos del premio</h2>
<p>Según el Estatuto Tributario, el premio es una ganancia ocasional para el ganador. Cuando su valor supere 48 UVT, debemos practicar una retención en la fuente del 20 % sobre el valor del premio antes de entregarlo. Si el premio es un bien (por ejemplo, un carro o una moto), el ganador debe pagar ese valor para que podamos entregarlo. Además, el ganador debe incluir el premio en su declaración de renta cuando corresponda.</p>

<h2>10. Boletas y verificación</h2>
<p>Cada boleta tiene un código único y un código QR. Puedes verificarla en este sitio en cualquier momento. Una boleta que no aparezca como válida en la verificación no participa. No respondemos por boletas compradas a personas o páginas distintas a nuestros canales oficiales.</p>

<h2>11. Uso del sitio y de WhatsApp</h2>
<ul>
<li>Te comprometes a dar datos ciertos y a no usar el sitio para fraudes, apartados masivos sin intención de pago o cualquier uso que afecte a otros participantes.</li>
<li>Podemos suspender apartados o compras que muestren señales de fraude mientras lo revisamos.</li>
<li>Los mensajes del bot son informativos. Si algo no queda claro, puedes pedir hablar con una persona del equipo.</li>
</ul>

<h2>12. Responsabilidad</h2>
<p>Respondemos por la realización del sorteo y la entrega de los premios ofrecidos, en los términos de la autorización de cada rifa y de la ley. No respondemos por fallas de la lotería que define el resultado, de las entidades financieras o de WhatsApp, ni por pagos enviados a cuentas que no sean las publicadas.</p>

<h2>13. Datos personales</h2>
<p>Tratamos tus datos según nuestra <a href="/politica-de-privacidad">Política de tratamiento de datos personales</a>, conforme a la Ley 1581 de 2012.</p>

<h2>14. Quejas y reclamos</h2>
<p>Puedes presentar peticiones, quejas o reclamos a {correo} o por WhatsApp al {whatsapp}. Te respondemos dentro de los quince (15) días hábiles siguientes. Si no quedas satisfecho, puedes acudir a la Superintendencia de Industria y Comercio o a la autoridad que autorizó la rifa (Coljuegos, en rifas nacionales).</p>

<h2>15. Cambios a estos términos</h2>
<p>Podemos actualizar estos términos. La versión vigente es la publicada en esta página, con su fecha de actualización. Los cambios no afectan las boletas ya compradas de una rifa en curso.</p>

<h2>16. Ley aplicable</h2>
<p>Estos términos se rigen por las leyes de la República de Colombia.</p>
HTML;
    }

    protected static function privacy(): string
    {
        return <<<'HTML'
<h2>1. Responsable del tratamiento</h2>
<p><strong>{razon_social}</strong>, NIT <strong>{nit}</strong>, marca <strong>{empresa}</strong>. Correo: {correo}. Teléfono: {telefono}. WhatsApp: {whatsapp}. Sitio: {sitio}.</p>
<p>Esta política cumple la Ley 1581 de 2012, el Decreto 1074 de 2015 (que compila el Decreto 1377 de 2013) y demás normas de protección de datos personales en Colombia.</p>

<h2>2. Qué datos recogemos</h2>
<ul>
<li>Nombre, número de celular y número de documento cuando lo pedimos para entregar un premio.</li>
<li>Los números que apartas o compras, tus pagos y las fotos de los comprobantes que envías.</li>
<li>Los mensajes que nos escribes por WhatsApp.</li>
<li>Datos técnicos básicos del sitio, como la dirección IP y la fecha de las visitas, para seguridad y para evitar fraudes.</li>
</ul>
<p>No recogemos datos sensibles ni datos de menores de edad. Los comprobantes de pago pueden mostrar datos bancarios; los guardamos solo para verificar el pago y con acceso restringido.</p>

<h2>3. Para qué los usamos</h2>
<ul>
<li>Apartar tus números, verificar tus pagos y emitir tu boleta.</li>
<li>Enviarte por WhatsApp la boleta, recordatorios del pago y del sorteo, y los resultados.</li>
<li>Contactar y verificar la identidad del ganador y entregar el premio.</li>
<li>Cumplir obligaciones legales: reportes a la autoridad que autoriza las rifas, retenciones y obligaciones tributarias, y atención de requerimientos de autoridades.</li>
<li>Atender tus preguntas, quejas y reclamos.</li>
<li>Enviarte información de nuevas rifas, solo si nos autorizas. Puedes dejar de recibirla en cualquier momento pidiéndolo por WhatsApp o al correo {correo}.</li>
</ul>

<h2>4. Autorización</h2>
<p>Cuando nos escribes por WhatsApp, apartas un número o compras una boleta, nos autorizas a tratar tus datos para las finalidades de esta política. Guardamos prueba de esa autorización.</p>

<h2>5. Con quién los compartimos</h2>
<ul>
<li>Proveedores que nos ayudan a operar, como el servicio de mensajería de WhatsApp y el de alojamiento del sitio, que solo pueden usarlos para prestarnos ese servicio. Algunos de ellos pueden guardar la información fuera de Colombia, con niveles adecuados de protección.</li>
<li>La autoridad que autoriza la rifa, la DIAN y otras autoridades, cuando la ley lo exige.</li>
<li>En la publicación de resultados mostramos solo el primer nombre, la inicial del apellido y las últimas cifras del celular del ganador.</li>
</ul>
<p>No vendemos tus datos.</p>

<h2>6. Tus derechos</h2>
<p>Como titular puedes:</p>
<ul>
<li>Conocer, actualizar y corregir tus datos.</li>
<li>Pedir prueba de la autorización que nos diste.</li>
<li>Saber cómo usamos tus datos.</li>
<li>Revocar la autorización o pedir que borremos tus datos, cuando no tengamos un deber legal o contractual de conservarlos (por ejemplo, los registros de una rifa y de los pagos se guardan por el tiempo que exigen las normas tributarias y de juegos de suerte y azar).</li>
<li>Presentar quejas ante la Superintendencia de Industria y Comercio, después de haber agotado el trámite con nosotros.</li>
</ul>

<h2>7. Cómo ejercerlos</h2>
<p>Escríbenos a {correo} o por WhatsApp al {whatsapp}, indicando tu nombre, tu celular y lo que necesitas.</p>
<ul>
<li>Las consultas se responden en máximo diez (10) días hábiles.</li>
<li>Los reclamos (corrección, actualización o eliminación) se responden en máximo quince (15) días hábiles.</li>
</ul>
<p>Si necesitamos más tiempo te avisamos antes del vencimiento, con el motivo, conforme a los artículos 14 y 15 de la Ley 1581 de 2012.</p>

<h2>8. Seguridad</h2>
<p>Protegemos tus datos con acceso restringido por roles, conexiones cifradas y almacenamiento privado de los comprobantes de pago. Solo el personal autorizado puede verlos.</p>

<h2>9. Vigencia</h2>
<p>Esta política rige desde su publicación en esta página. Conservamos tus datos mientras sean necesarios para las finalidades descritas y para cumplir la ley. Si la cambiamos de forma importante, lo publicaremos aquí con la nueva fecha.</p>
HTML;
    }
}
