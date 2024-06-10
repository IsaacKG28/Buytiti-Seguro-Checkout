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
    if ( $total_compra >= 1 && $total_compra < 2000 ) {
        return 35;
    } elseif ( $total_compra >= 2000 && $total_compra < 5000 ) {
        return 50;
    } elseif ( $total_compra >= 5000 && $total_compra < 10000 ) {
        return 100;
    } elseif ( $total_compra >= 10000 && $total_compra < 20000 ) {
        return 150;
    } elseif ( $total_compra >= 20000 && $total_compra <= 30000 ) {
        return 300;
    } else {
        return 0;
    }
}

// Enqueue SweetAlert
function enqueue_sweetalert() {
    wp_enqueue_script('sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_sweetalert');

// Añadir el input radio para el seguro antes de la fila order-total
add_action( 'woocommerce_review_order_before_order_total', 'mostrar_input_seguro' );
function mostrar_input_seguro() {
    ?>
    <tr class="seguro-compra">
        <td colspan="2">
            <label>
                <input type="radio" name="seguro_compra" value="yes" <?php checked( get_option( 'seguro_compra' ), 'yes' ); ?>> 
                <?php _e( 'Agregar Seguro de Compra.', 'woocommerce' ); ?>
            </label>
            <br>
            <label>
                <input type="radio" name="seguro_compra" value="no" <?php checked( get_option( 'seguro_compra' ), 'no' ); ?>> 
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
                    mensaje = 'Éxito: Haz agregado el seguro correctamente.';
                    icono = 'success';
                } else {
                    mensaje = 'Cuidado: Estás quitando el seguro de compra.';
                    icono = 'error';
                }
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'actualizar_seguro',
                        seguro: seguro
                    },
                    success: function(response) {
                        Swal.fire({
                            title: mensaje,
                            icon: icono,
                            confirmButtonText: 'OK'
                        });
                        location.reload();
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
    $seguro = $_POST['seguro'];
    update_option( 'seguro_compra', $seguro );
    wp_die();
}

// Añadir o quitar el costo del seguro basado en la selección del cliente
add_action( 'woocommerce_cart_calculate_fees', 'agregar_costo_seguro' );
function agregar_costo_seguro( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $total_compra = $cart->cart_contents_total;
    $costo_seguro = calcular_costo_seguro( $total_compra );
    $seguro = get_option( 'seguro_compra', 'no' );

    if ( $seguro === 'yes' && $costo_seguro > 0 ) {
        $cart->add_fee( 'Seguro de Compra', $costo_seguro, true );
    }
}
?>
