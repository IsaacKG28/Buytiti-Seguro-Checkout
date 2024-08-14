<?php
/*
Plugin Name: Buytiti - Seguro - Checkout
Description: Añade un costo extra basado en el total de la compra como seguro de compra.
Version: 1.6
Author: Fernando Isaac González Medina
*/

// Evitar el acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Calcular el costo del seguro
function calcular_costo_seguro( $total_compra ) {
    if ( $total_compra >= 500 && $total_compra < 999 ) {
        return 50;
    } elseif ( $total_compra >= 1000 && $total_compra <= 1999 ) {
        return 100;
    } elseif ( $total_compra >= 2000 && $total_compra <= 2999 ) {
        return 200;
    } elseif ( $total_compra >= 3000 && $total_compra <= 5000 ) {
        return 500;
    } elseif ( $total_compra >= 5001 && $total_compra <= 25000 ) {
        return 500;
    } else {
        return 0;
    }
}

// Enqueue SweetAlert
function enqueue_sweetalert() {
    wp_enqueue_script('sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array('jquery'), null, true);
    wp_localize_script('sweetalert', 'my_plugin_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('my_custom_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_sweetalert');

// function preseleccionar_seguro() {
//     if ( is_checkout() && ! is_wc_endpoint_url() ) {
//         if ( ! WC()->session->get( 'seguro_compra' ) ) {
//             WC()->session->set( 'seguro_compra', 'yes' );
//         }
//     }
// }
// add_action( 'template_redirect', 'preseleccionar_seguro' );

// Añadir el input radio para el seguro antes de la fila order-total
add_action( 'woocommerce_review_order_before_order_total', 'mostrar_input_seguro' );
function mostrar_input_seguro() {
    $seguro_compra = WC()->session->get( 'seguro_compra', 'no' ); // Cambiado a 'no' por defecto
    ?>
    <tr class="seguro-compra" style="float: left;">
        <td colspan="2">
            <label id="label-agregar-seguro" style="display: <?php echo $seguro_compra === 'no' ? 'block' : 'none'; ?>">
                <input type="radio" name="seguro_compra" value="yes" <?php checked( $seguro_compra, 'yes' ); ?>> 
                <?php _e( 'Agregar Seguro de Compra.', 'woocommerce' ); ?>
            </label>
            <br>
            <label id="label-quitar-seguro" style="display: <?php echo $seguro_compra === 'yes' ? 'block' : 'none'; ?>">
                <input type="radio" name="seguro_compra" value="no" <?php checked( $seguro_compra, 'no' ); ?>> 
                <?php _e( 'Quitar Seguro de Compra.', 'woocommerce' ); ?>
            </label>
        </td>
    </tr>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        $('input[name="seguro_compra"]').change(function(){
            var seguro = $(this).val();
            var mensaje, icono;
            if (seguro === 'yes') {
                mensaje = 'Excelente tu pedido contará con seguro en caso de robo o extravío. Consulta los <a href="https://buytiti.com/terminos-y-condiciones/#seguro-paquetes" target="_blank">términos y condiciones aquí</a>.';
                icono = 'success';
                $('#label-agregar-seguro').hide();
                $('#label-quitar-seguro').show();
            } else {
                mensaje = 'Evita imprevistos. Recuerda que sin seguro NO habrá ningún reembolso en caso de robo o extravío. Consulta los <a href="https://buytiti.com/terminos-y-condiciones/#seguro-paquetes" target="_blank">términos y condiciones aquí</a>.';
                icono = 'error';
                $('#label-agregar-seguro').show();
                $('#label-quitar-seguro').hide();
            }
            Swal.fire({
                title: mensaje,
                icon: icono,
                confirmButtonText: 'OK',
                html: true // Esto permite el uso de HTML en el mensaje
            });

            $.ajax({
                type: 'POST',
                url: my_plugin_ajax.ajax_url,
                data: {
                    action: 'actualizar_seguro',
                    seguro: seguro,
                    security: my_plugin_ajax.security
                },
                success: function(response) {
                    console.log('Seguro actualizado correctamente.');
                    // Actualiza solo la sección del carrito
                    $('body').trigger('update_checkout');
                }
            });
        });
    });
</script>

    <?php
}

// Manejar la lógica para agregar o quitar el costo del seguro
add_action( 'wp_ajax_actualizar_seguro', 'actualizar_seguro' );
add_action( 'wp_ajax_nopriv_actualizar_seguro', 'actualizar_seguro' );
function actualizar_seguro() {
    check_ajax_referer('my_custom_nonce', 'security');

    $seguro = sanitize_text_field($_POST['seguro']);
    WC()->session->set('seguro_compra', $seguro);

    // Recalcular los totales del carrito
    WC()->cart->calculate_totals();

    wp_die();
}

// Añadir o quitar el costo del seguro basado en la selección del cliente
add_action('woocommerce_cart_calculate_fees', 'agregar_costo_seguro');
function agregar_costo_seguro($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    $total_compra = $cart->cart_contents_total;
    $costo_seguro = calcular_costo_seguro($total_compra);
    $seguro = WC()->session->get('seguro_compra', 'no'); // Cambiado a 'no' por defecto

    if ($seguro === 'yes' && $costo_seguro > 0) {
        $cart->add_fee('Seguro de Compra', $costo_seguro, true);
    }
}
?>
