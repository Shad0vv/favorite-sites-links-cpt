<?php
/*
Plugin Name: Favorite Sites Links (CPT)
Description: Плагин для отображения списка любимых сайтов через Custom Post Type с импортом, проверкой дубликатов и кастомными стилями
Version: 2.3
Author: Andrew Arutunyan & Grok
*/

// Регистрация Custom Post Type
function fsl_register_post_type() {
    $labels = array(
        'name' => 'Любимые сайты',
        'singular_name' => 'Любимый сайт',
        'menu_name' => 'Любимые сайты',
        'add_new' => 'Добавить новый',
        'add_new_item' => 'Добавить новый сайт',
        'edit_item' => 'Редактировать сайт',
        'new_item' => 'Новый сайт',
        'view_item' => 'Просмотреть сайт',
        'search_items' => 'Искать сайты',
        'not_found' => 'Сайты не найдены',
        'not_found_in_trash' => 'Сайты не найдены в корзине',
    );

    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'supports' => array('title'),
        'menu_icon' => 'dashicons-star-filled',
        'show_in_rest' => true,
    );

    register_post_type('favorite_site', $args);
}
add_action('init', 'fsl_register_post_type');

// Регистрация таксономии для категорий
function fsl_register_taxonomy() {
    $labels = array(
        'name' => 'Категории сайтов',
        'singular_name' => 'Категория сайта',
        'search_items' => 'Искать категории',
        'all_items' => 'Все категории',
        'edit_item' => 'Редактировать категорию',
        'update_item' => 'Обновить категорию',
        'add_new_item' => 'Добавить новую категорию',
        'new_item_name' => 'Название новой категории',
        'menu_name' => 'Категории',
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'rewrite' => false,
    );

    register_taxonomy('site_category', 'favorite_site', $args);
}
add_action('init', 'fsl_register_taxonomy');

// Добавляем метабоксы
function fsl_add_meta_boxes() {
    add_meta_box(
        'fsl_site_details',
        'Детали сайта',
        'fsl_site_details_callback',
        'favorite_site',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'fsl_add_meta_boxes');

function fsl_site_details_callback($post) {
    wp_nonce_field('fsl_save_meta_box_data', 'fsl_meta_box_nonce');
    $url = get_post_meta($post->ID, '_fsl_url', true);
    $description = get_post_meta($post->ID, '_fsl_description', true);
    ?>
    <p>
        <label for="fsl_url">URL сайта:</label><br>
        <input type="url" id="fsl_url" name="fsl_url" value="<?php echo esc_attr($url); ?>" style="width: 100%;">
    </p>
    <p>
        <label for="fsl_description">Описание:</label><br>
        <textarea id="fsl_description" name="fsl_description" rows="4" style="width: 100%;"><?php echo esc_textarea($description); ?></textarea>
    </p>
    <?php
}

function fsl_save_meta_box_data($post_id) {
    if (!isset($_POST['fsl_meta_box_nonce']) || !wp_verify_nonce($_POST['fsl_meta_box_nonce'], 'fsl_save_meta_box_data')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['fsl_url'])) {
        update_post_meta($post_id, '_fsl_url', esc_url_raw($_POST['fsl_url']));
    }

    if (isset($_POST['fsl_description'])) {
        update_post_meta($post_id, '_fsl_description', sanitize_textarea_field($_POST['fsl_description']));
    }
}
add_action('save_post', 'fsl_save_meta_box_data');

// Регистрируем настройки
function fsl_register_settings() {
    add_options_page(
        'Настройки любимых сайтов',
        'Настройки сайтов',
        'manage_options',
        'favorite-sites-settings',
        'fsl_settings_page'
    );
}
add_action('admin_menu', 'fsl_register_settings');

function fsl_register_options() {
    register_setting('fsl_options_group', 'fsl_columns', array('default' => '2'));
    register_setting('fsl_options_group', 'fsl_target', array('default' => '_blank'));
    register_setting('fsl_options_group', 'fsl_heading_level', array('default' => 'h3'));
    register_setting('fsl_options_group', 'fsl_custom_css', array('default' => ''));
}
add_action('admin_init', 'fsl_register_options');

// Страница настроек с импортом и кастомными стилями
function fsl_settings_page() {
    if (isset($_POST['fsl_import']) && !empty($_POST['fsl_import_text'])) {
        fsl_import_sites($_POST['fsl_import_text']);
    }
    ?>
    <div class="wrap">
        <h1>Настройки любимых сайтов</h1>
        <form method="post" action="options.php">
            <?php settings_fields('fsl_options_group'); ?>
            <h3>Настройки отображения</h3>
            <p>
                <label>Количество колонок:</label><br>
                <input type="radio" name="fsl_columns" value="1" <?php checked('1', get_option('fsl_columns')); ?>> Одна колонка<br>
                <input type="radio" name="fsl_columns" value="2" <?php checked('2', get_option('fsl_columns')); ?>> Две колонки
            </p>
            <p>
                <label>Открывать ссылки:</label><br>
                <input type="radio" name="fsl_target" value="_self" <?php checked('_self', get_option('fsl_target')); ?>> В том же окне<br>
                <input type="radio" name="fsl_target" value="_blank" <?php checked('_blank', get_option('fsl_target')); ?>> В новом окне
            </p>
            <p>
                <label>Уровень заголовка категорий:</label><br>
                <select name="fsl_heading_level">
                    <option value="h1" <?php selected('h1', get_option('fsl_heading_level')); ?>>H1</option>
                    <option value="h2" <?php selected('h2', get_option('fsl_heading_level')); ?>>H2</option>
                    <option value="h3" <?php selected('h3', get_option('fsl_heading_level')); ?>>H3</option>
                    <option value="h4" <?php selected('h4', get_option('fsl_heading_level')); ?>>H4</option>
                    <option value="h5" <?php selected('h5', get_option('fsl_heading_level')); ?>>H5</option>
                    <option value="h6" <?php selected('h6', get_option('fsl_heading_level')); ?>>H6</option>
                </select>
            </p>
            <p>
                <label for="fsl_custom_css">Дополнительные CSS-стили:</label><br>
                <textarea name="fsl_custom_css" id="fsl_custom_css" rows="20" cols="150" placeholder="Введите свои CSS-стили для кастомизации списка сайтов"><?php echo esc_textarea(get_option('fsl_custom_css')); ?></textarea>
                <br><small>Пример: .favorite-sites-list a { color: #ff0000; }</small>
            </p>
            <?php submit_button(); ?>
        </form>

        <h3>Импорт сайтов из текста</h3>
        <form method="post" action="">
            <p>
                <textarea name="fsl_import_text" rows="20" cols="150" placeholder="Вставьте список сайтов в формате: Категория | Название | URL | Описание (описание необязательно)"></textarea>
            </p>
            <p>Пример:<br>
            Поиск | Google | https://google.com | Лучшая поисковая система<br>
            Энциклопедии | Wikipedia | https://wikipedia.org</p>
            <input type="submit" name="fsl_import" class="button button-primary" value="Импортировать сайты">
        </form>
    </div>
    <?php
}

// Функция импорта с проверкой дубликатов
function fsl_import_sites($text) {
    $lines = explode("\n", trim($text));
    $imported = 0;
    $updated = 0;

    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) >= 3) {
            $category = trim($parts[0]);
            $name = trim($parts[1]);
            $url = trim($parts[2]);
            $description = isset($parts[3]) ? trim($parts[3]) : '';

            // Проверка на дубликат по названию и URL
            $existing_post = get_posts(array(
                'post_type' => 'favorite_site',
                'post_status' => 'publish',
                'title' => $name,
                'meta_query' => array(
                    array(
                        'key' => '_fsl_url',
                        'value' => $url,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1,
            ));

            if (!empty($existing_post)) {
                // Обновляем существующую запись
                $post_id = $existing_post[0]->ID;
                if ($description) {
                    update_post_meta($post_id, '_fsl_description', sanitize_textarea_field($description));
                }
                
                // Обновляем категорию
                $term = term_exists($category, 'site_category');
                if (!$term) {
                    $term = wp_insert_term($category, 'site_category');
                }
                if (!is_wp_error($term)) {
                    wp_set_post_terms($post_id, array((int)$term['term_id']), 'site_category');
                }
                $updated++;
            } else {
                // Создаем новую запись
                $post_id = wp_insert_post(array(
                    'post_title' => $name,
                    'post_type' => 'favorite_site',
                    'post_status' => 'publish',
                ));

                if ($post_id && !is_wp_error($post_id)) {
                    // Сохраняем метаданные
                    update_post_meta($post_id, '_fsl_url', esc_url_raw($url));
                    if ($description) {
                        update_post_meta($post_id, '_fsl_description', sanitize_textarea_field($description));
                    }

                    // Присваиваем категорию
                    $term = term_exists($category, 'site_category');
                    if (!$term) {
                        $term = wp_insert_term($category, 'site_category');
                    }
                    if (!is_wp_error($term)) {
                        wp_set_post_terms($post_id, array((int)$term['term_id']), 'site_category');
                    }

                    $imported++;
                }
            }
        }
    }

    $message = '';
    if ($imported > 0) {
        $message .= 'Успешно импортировано ' . $imported . ' новых сайтов. ';
    }
    if ($updated > 0) {
        $message .= 'Обновлено ' . $updated . ' существующих сайтов.';
    }
    if ($message) {
        echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
    }
}

// Шорткод для вывода ссылок
function fsl_display_links($atts) {
    $columns = get_option('fsl_columns', '2');
    $target = get_option('fsl_target', '_blank');
    $heading_level = get_option('fsl_heading_level', 'h3');
    $custom_css = get_option('fsl_custom_css', '');

    $args = array(
        'post_type' => 'favorite_site',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );

    $sites = new WP_Query($args);
    if (!$sites->have_posts()) return '<p>Список сайтов пуст</p>';

    $categories = array();
    while ($sites->have_posts()) {
        $sites->the_post();
        $terms = get_the_terms(get_the_ID(), 'site_category');
        $category = $terms && !is_wp_error($terms) ? $terms[0]->name : 'Без категории';
        $url = get_post_meta(get_the_ID(), '_fsl_url', true);
        $description = get_post_meta(get_the_ID(), '_fsl_description', true);

        $categories[$category][] = array(
            'name' => get_the_title(),
            'url' => $url,
            'description' => $description
        );
    }
    wp_reset_postdata();

    $output = '<div class="favorite-sites" data-columns="' . esc_attr($columns) . '">';
    
    // Добавляем кастомные стили, если они есть
    if (!empty($custom_css)) {
        $output .= '<style>' . wp_strip_all_tags($custom_css) . '</style>';
    }
    
    if ($columns == '2') {
        $total_categories = count($categories);
        $half = ceil($total_categories / 2);
        $left_column = array_slice($categories, 0, $half, true);
        $right_column = array_slice($categories, $half, null, true);
        
        $output .= '<div class="fsl-column fsl-left">';
        foreach ($left_column as $category => $links) {
            $output .= sprintf('<%s>%s</%s>', $heading_level, esc_html($category), $heading_level);
            $output .= '<ul class="favorite-sites-list">';
            foreach ($links as $link) {
                $description = $link['description'] ? ' — ' . esc_html($link['description']) : '';
                $output .= sprintf(
                    '<li><a href="%s" target="%s" rel="noopener noreferrer">%s</a>%s</li>',
                    esc_url($link['url']),
                    esc_attr($target),
                    esc_html($link['name']),
                    $description
                );
            }
            $output .= '</ul>';
        }
        $output .= '</div>';
        
        $output .= '<div class="fsl-column fsl-right">';
        foreach ($right_column as $category => $links) {
            $output .= sprintf('<%s>%s</%s>', $heading_level, esc_html($category), $heading_level);
            $output .= '<ul class="favorite-sites-list">';
            foreach ($links as $link) {
                $description = $link['description'] ? ' — ' . esc_html($link['description']) : '';
                $output .= sprintf(
                    '<li><a href="%s" target="%s" rel="noopener noreferrer">%s</a>%s</li>',
                    esc_url($link['url']),
                    esc_attr($target),
                    esc_html($link['name']),
                    $description
                );
            }
            $output .= '</ul>';
        }
        $output .= '</div>';
    } else {
        foreach ($categories as $category => $links) {
            $output .= sprintf('<%s>%s</%s>', $heading_level, esc_html($category), $heading_level);
            $output .= '<ul class="favorite-sites-list">';
            foreach ($links as $link) {
                $description = $link['description'] ? ' — ' . esc_html($link['description']) : '';
                $output .= sprintf(
                    '<li><a href="%s" target="%s" rel="noopener noreferrer">%s</a>%s</li>',
                    esc_url($link['url']),
                    esc_attr($target),
                    esc_html($link['name']),
                    $description
                );
            }
            $output .= '</ul>';
        }
    }
    
    $output .= '</div>';
    return $output;
}
add_shortcode('favorite_sites', 'fsl_display_links');

// Добавляем стили
function fsl_add_styles() {
    wp_enqueue_style(
        'fsl-styles',
        plugin_dir_url(__FILE__) . 'fsl-styles.css'
    );
}
add_action('wp_enqueue_scripts', 'fsl_add_styles');