<?php
/**
 * Plugin Name: Массовые действия с записями по ID
 * Plugin URI: https://github.com/elizarov-n906/bulk-post-actions-by-id
 * Description: Инструмент для массового изменения статуса записей (черновик, ожидание, корзина) по списку ID.
 * Version: 1.1.0
 * Author: Elizarov_n906
 * Author URI: https://github.com/elizarov-n906
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bulk-post-actions-by-id
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Защита от прямого доступа
}

// =============================================================================
// 1. ССЫЛКА "НАСТРОЙКИ" В СПИСКЕ ПЛАГИНОВ
// =============================================================================
function bpbai_add_settings_link( $links, $file ) {
    if ( $file === plugin_basename( __FILE__ ) ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=bulk-post-actions-by-id' ) . '" style="font-weight: bold;">Настройки</a>';
        array_unshift( $links, $settings_link );
    }
    return $links;
}
add_filter( 'plugin_action_links', 'bpbai_add_settings_link', 10, 2 );

// =============================================================================
// 2. МЕНЮ В РАЗДЕЛЕ "КОНСОЛЬ" (index.php)
// =============================================================================
function bpbai_add_admin_menu() {
    add_submenu_page(
        'index.php', // Родительский slug (Консоль)
        'Массовые действия с записями по ID', // Заголовок страницы
        'Массовые действия', // Текст в меню (короче, чтобы не занимать много места)
        'manage_options', // Требуемые права доступа
        'bulk-post-actions-by-id', // Slug страницы
        'bpbai_render_admin_page' // Функция отрисовки
    );
}
add_action( 'admin_menu', 'bpbai_add_admin_menu' );

// =============================================================================
// 3. СТРАНИЦА НАСТРОЕК И ЛОГИКА
// =============================================================================
function bpbai_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $message = '';
    $error_message = '';

    // Обработка отправки формы
    if ( isset( $_POST['bpbai_submit'] ) && check_admin_referer( 'bpbai_action_nonce', 'bpbai_nonce' ) ) {
        $ids_input = sanitize_textarea_field( $_POST['bpbai_ids'] );
        $action_type = sanitize_text_field( $_POST['bpbai_action'] );

        // Разделяем ID по запятой, пробелу или переносу строки
        $ids_array = preg_split( '/[\s,]+/', $ids_input, -1, PREG_SPLIT_NO_EMPTY );
        
        $success_count = 0;
        $fail_count = 0;
        $processed_ids = [];

        foreach ( $ids_array as $id ) {
            $post_id = (int) $id;
            if ( $post_id <= 0 ) {
                continue;
            }

            $post = get_post( $post_id );
            if ( ! $post ) {
                $fail_count++;
                continue;
            }

            // Подготовка данных для обновления
            $update_data = array( 'ID' => $post_id );

            if ( $action_type === 'draft' ) {
                $update_data['post_status'] = 'draft';
            } elseif ( $action_type === 'pending' ) {
                $update_data['post_status'] = 'pending';
            } elseif ( $action_type === 'trash' ) {
                $update_data['post_status'] = 'trash';
            }

            $result = wp_update_post( $update_data, true );

            if ( is_wp_error( $result ) ) {
                $fail_count++;
            } else {
                $success_count++;
                $processed_ids[] = $post_id;
            }
        }

        if ( $success_count > 0 ) {
            $message = sprintf( 
                'Успешно обработано записей: %d. ID: %s', 
                $success_count, 
                implode( ', ', $processed_ids ) 
            );
        }
        if ( $fail_count > 0 ) {
            $error_message = sprintf( 
                'Не удалось обработать записей: %d. Проверьте корректность ID.', 
                $fail_count 
            );
        }
    }

    // Вывод интерфейса
    ?>
    <!-- Требование: class="elizarov_n906" используется ровно один раз -->
    <div class="wrap elizarov_n906">
        <h1>Массовые действия с записями по ID</h1>
        
        <?php if ( ! empty( $message ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( $message ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $error_message ) ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html( $error_message ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'bpbai_action_nonce', 'bpbai_nonce' ); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Список ID записей</th>
                    <td>
                        <textarea name="bpbai_ids" id="bpbai_ids" rows="8" cols="50" class="large-text" placeholder="123, 124, 125&#10;или&#10;123&#10;124&#10;125" required></textarea>
                        <p class="description">Введите ID записей, разделяя их запятой или переносом строки.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Целевое действие</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="bpbai_action" value="draft" checked>
                                Переместить в черновики
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="bpbai_action" value="pending">
                                Переместить в запланированные (на модерацию)
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="bpbai_action" value="trash">
                                Переместить в корзину
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Выполнить действие', 'primary', 'bpbai_submit' ); ?>
        </form>

        <hr style="margin-top: 40px;">
        
        <!-- Требование: Автор плагина внизу -->
        <p class="description" style="color: #666; font-size: 13px; border-top: 1px solid #ccc; padding-top: 15px;">
            Автор плагина: <strong>elizarov_n906</strong>
        </p>
    </div>
    <?php
}