<?php

class ESP_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function add_admin_menu() {
        add_menu_page( __( 'EVE Skills Settings', 'eve-skill-plugin' ), __( 'EVE Skills', 'eve-skill-plugin' ), 'edit_others_pages', 'eve_skill_plugin_settings', array($this, 'render_settings_page'), 'dashicons-id-alt');
        //add_submenu_page( 'eve_skill_plugin_settings', __( 'My Linked EVE Characters', 'eve-skill-plugin' ), __( 'My Linked Characters', 'eve-skill-plugin' ), 'read', 'eve_skill_user_characters_page', array($this, 'render_user_characters_page'));
        add_submenu_page( 'eve_skill_plugin_settings', __( 'View All User EVE Skills', 'eve-skill-plugin' ), __( 'View All User Skills', 'eve-skill-plugin' ), 'manage_options', 'eve_view_all_user_skills', array($this, 'render_view_all_user_skills_page'));
    }

    public function register_plugin_settings() {
        register_setting( 'esp_settings_group', 'esp_client_id' );
        register_setting( 'esp_settings_group', 'esp_client_secret' );
        register_setting( 'esp_settings_group', 'esp_scopes', ['default' => 'esi-skills.read_skills.v1 publicData']);
    }

    public function render_settings_page() {
        // Content of original esp_render_settings_page()
        if ( ! current_user_can( 'edit_others_pages' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'eve-skill-plugin' ) ); return; } ?>
        <div class="wrap"> <h1><?php esc_html_e( 'EVE Online Skill Viewer Settings', 'eve-skill-plugin' ); ?></h1> <form method="post" action="options.php"> <?php settings_fields( 'esp_settings_group' ); do_settings_sections( 'esp_settings_group' ); ?> <table class="form-table"> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Client ID', 'eve-skill-plugin' ); ?></th> <td><input type="text" name="esp_client_id" value="<?php echo esc_attr( get_option( 'esp_client_id' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Secret Key', 'eve-skill-plugin' ); ?></th> <td><input type="password" name="esp_client_secret" value="<?php echo esc_attr( get_option( 'esp_client_secret' ) ); ?>" size="60" /></td> </tr> <tr valign="top"> <th scope="row"><?php esc_html_e( 'EVE Application Scopes', 'eve-skill-plugin' ); ?></th> <td> <input type="text" name="esp_scopes" value="<?php echo esc_attr( get_option( 'esp_scopes', 'esi-skills.read_skills.v1 publicData' ) ); ?>" size="60" /> <p class="description"><?php esc_html_e( 'Space separated. Default: esi-skills.read_skills.v1 publicData', 'eve-skill-plugin' ); ?></p> </td> </tr> </table> <?php submit_button(); ?> </form> <hr/> <h2><?php esc_html_e( 'Callback URL for EVE Application', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'Use this URL as the "Callback URL" or "Redirect URI" in your EVE Online application settings:', 'eve-skill-plugin' ); ?></p> <code><?php echo esc_url( admin_url( 'admin-post.php?action=' . ESP_SSO_CALLBACK_ACTION_NAME ) ); ?></code> <hr/> <h2><?php esc_html_e( 'Shortcode for Login Button', 'eve-skill-plugin' ); ?></h2> <p><?php esc_html_e( 'To place an EVE Online login button on any page or post, use the following shortcode:', 'eve-skill-plugin' ); ?></p> <code>[eve_sso_login_button]</code> <p><?php esc_html_e( 'You can customize the button text like this:', 'eve-skill-plugin'); ?> <code>[eve_sso_login_button text="Link Your EVE Character"]</code></p> </div> <?php
    }

    public function render_user_characters_page() {
        // Content of original esp_render_user_characters_page()
        // Make sure to use ESP_Helpers::display_sso_message()
        $current_user_id = get_current_user_id(); 
        if (!$current_user_id) { echo "<p>" . __("Please log in to view this page.", "eve-skill-plugin") . "</p>"; return; }
        $main_char_id = get_user_meta($current_user_id, 'esp_main_eve_character_id', true);
        $client_id = get_option('esp_client_id');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'My Linked EVE Characters', 'eve-skill-plugin' ); ?></h1>
            <?php if ( isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { ESP_Helpers::display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); } ?>
            <?php if ( ! $client_id ) : ?> <p style="color:red;"><?php esc_html_e( 'EVE Application Client ID is not configured.', 'eve-skill-plugin' ); ?></p> <?php return; endif; ?>

            <?php if ( $main_char_id ) : 
                $main_char_name = get_user_meta($current_user_id, 'esp_main_eve_character_name', true);
            ?>
                <h3><?php esc_html_e( 'Main Character', 'eve-skill-plugin' ); ?></h3>
                <p>
                    <?php printf( esc_html__( '%s (ID: %s)', 'eve-skill-plugin' ), esc_html( $main_char_name ), esc_html( $main_char_id ) ); ?>
                     - <a href="<?php echo esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $current_user_id, 'view_char_id' => $main_char_id], admin_url('admin.php'))); ?>"><?php esc_html_e('View Skills', 'eve-skill-plugin'); ?></a>
                </p>
                <?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' ); ?>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block; margin-right: 10px;"> <input type="hidden" name="action" value="esp_initiate_sso"> <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <input type="hidden" name="esp_auth_type" value="main"> <?php submit_button( __( 'Re-Auth/Switch Main', 'eve-skill-plugin' ), 'secondary', 'submit', false ); ?> </form>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block;"> <input type="hidden" name="action" value="esp_initiate_sso"> <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <input type="hidden" name="esp_auth_type" value="alt"> <?php submit_button( __( 'Authenticate Alt Character', 'eve-skill-plugin' ), 'primary', 'submit', false ); ?> </form>
                <h3><?php esc_html_e( 'Alt Characters', 'eve-skill-plugin' ); ?></h3>
                <?php
                $alt_characters = get_user_meta($current_user_id, 'esp_alt_characters', true);
                if (is_array($alt_characters) && !empty($alt_characters)) {
                    echo '<ul>';
                    foreach ($alt_characters as $alt_char) {
                        if (!is_array($alt_char) || !isset($alt_char['id']) || !isset($alt_char['name'])) continue;
                        echo '<li>'; printf(esc_html__('%s (ID: %s)', 'eve-skill-plugin'), esc_html($alt_char['name']), esc_html($alt_char['id']));
                        echo ' - <a href="'. esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $current_user_id, 'view_char_id' => $alt_char['id']], admin_url('admin.php'))) .'">'. esc_html__('View Skills', 'eve-skill-plugin') .'</a>';
                        echo ' <form method="post" action="'. esc_url( admin_url('admin-post.php') ) .'" style="display:inline-block; margin-left:10px;">'; echo '<input type="hidden" name="action" value="esp_remove_alt_character">'; echo '<input type="hidden" name="esp_alt_char_id_to_remove" value="'. esc_attr($alt_char['id']) .'">'; echo '<input type="hidden" name="esp_redirect_back_url" value="' . esc_url($current_admin_page_url) . '">'; wp_nonce_field('esp_remove_alt_action_' . $alt_char['id'], 'esp_remove_alt_nonce'); submit_button( __( 'Remove Alt', 'eve-skill-plugin' ), 'delete small', 'submit', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt character?', 'eve-skill-plugin')).'");'] ); echo '</form>';
                        echo '</li>';
                    } echo '</ul>';
                } else { echo '<p>' . esc_html__('No alt characters linked yet.', 'eve-skill-plugin') . '</p>'; } ?>
                <hr style="margin: 20px 0;">
                 <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"> <input type="hidden" name="action" value="esp_clear_all_eve_data_for_user"> <?php wp_nonce_field( 'esp_clear_all_eve_data_action', 'esp_clear_all_eve_data_nonce' ); ?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <?php submit_button( __( 'Clear All My EVE Data (Main & Alts)', 'eve-skill-plugin' ), 'delete', 'submit', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove ALL EVE data, including main and all alts?', 'eve-skill-plugin')).'");'] ); ?> </form>
            <?php else : ?>
                <p><?php esc_html_e( 'You have not linked your main EVE Online character yet.', 'eve-skill-plugin' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"> <input type="hidden" name="action" value="esp_initiate_sso"> <?php wp_nonce_field( 'esp_initiate_sso_action', 'esp_initiate_sso_nonce' ); ?> <?php $current_admin_page_url = admin_url( 'admin.php?page=eve_skill_user_characters_page' );?> <input type="hidden" name="esp_redirect_back_url" value="<?php echo esc_url($current_admin_page_url); ?>"> <input type="hidden" name="esp_auth_type" value="main"> <?php submit_button( __( 'Link Your Main EVE Character', 'eve-skill-plugin' ) ); ?> </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_view_all_user_skills_page() {
        // Content of original esp_render_view_all_user_skills_page()
        // Make sure to use ESP_Helpers methods where appropriate (e.g., ESP_Helpers::display_sso_message(), ESP_Helpers::get_alt_character_data_item())
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions.', 'eve-skill-plugin' ) ); }
        ?>
        <div class="wrap esp-admin-view">
            <h1><?php esc_html_e( 'View User EVE Skills', 'eve-skill-plugin' ); ?></h1>
            <style> .esp-admin-view .char-tree { list-style-type: none; padding-left: 0; } .esp-admin-view .char-tree ul { list-style-type: none; padding-left: 20px; margin-left: 10px; border-left: 1px dashed #ccc; } .esp-admin-view .char-item { padding: 5px 0; } .esp-admin-view .char-item strong { font-size: 1.1em; } .esp-admin-view .char-meta { font-size: 0.9em; color: #555; margin-left: 10px; } .esp-admin-view .char-actions a, .esp-admin-view .char-actions form { margin-left: 10px; display: inline-block; vertical-align: middle;} .esp-admin-view .main-char-item { border: 1px solid #0073aa; padding: 10px; margin-bottom:15px; background: #f7fcfe; } .esp-admin-view .alt-list-heading { margin-top: 15px; font-weight: bold; } .esp-admin-view .skill-table-container { margin-top: 20px; } .esp-admin-view .admin-action-button { padding: 2px 5px !important; font-size: 0.8em !important; line-height: 1.2 !important; height: auto !important; min-height: 0 !important;} .esp-admin-view .assign-alt-form select {vertical-align: baseline; margin: 0 5px;} </style>
            <?php
             if ( isset( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ) { ESP_Helpers::display_sso_message( sanitize_key( $_GET[ESP_REDIRECT_MESSAGE_QUERY_ARG] ) ); }
            $selected_user_id = isset( $_GET['view_user_id'] ) ? intval( $_GET['view_user_id'] ) : 0;
            $selected_char_id_to_view_skills = isset( $_GET['view_char_id'] ) ? intval( $_GET['view_char_id'] ) : 0;

            if ( $selected_user_id > 0 ) { 
                $user_info = get_userdata( $selected_user_id );
                if ( ! $user_info ) { echo '<p>' . esc_html__( 'WordPress user not found.', 'eve-skill-plugin' ) . '</p>'; echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>'; echo '</div>'; return; }
                echo '<h2>' . sprintf( esc_html__( 'EVE Characters for: %s', 'eve-skill-plugin' ), esc_html( $user_info->display_name ) ) . ' (' . esc_html($user_info->user_login) . ')</h2>';
                if ( $selected_char_id_to_view_skills > 0 ) { 
                    $is_main_view = ($selected_char_id_to_view_skills == get_user_meta( $selected_user_id, 'esp_main_eve_character_id', true ));
                    $char_name_to_display = $is_main_view ? get_user_meta($selected_user_id, 'esp_main_eve_character_name', true) : ESP_Helpers::get_alt_character_data_item($selected_user_id, $selected_char_id_to_view_skills, 'name');
                    echo '<h3>' . sprintf( esc_html__( 'Skills for %s (ID: %s)', 'eve-skill-plugin' ), esc_html( $char_name_to_display ), esc_html($selected_char_id_to_view_skills) ) . '</h3>'; echo '<div class="skill-table-container">'; $this->display_character_skills_for_admin( $selected_user_id, $selected_char_id_to_view_skills ); echo '</div>';
                    echo '<p><a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id], admin_url('admin.php'))) . '">« ' . esc_html__( 'Back to character list for this user', 'eve-skill-plugin' ) . '</a></p>';
                } else { 
                    $main_char_id = get_user_meta( $selected_user_id, 'esp_main_eve_character_id', true );
                    $alt_characters = get_user_meta($selected_user_id, 'esp_alt_characters', true);
                    if (!is_array($alt_characters)) $alt_characters = []; 

                    echo '<ul class="char-tree">';
                    if ( $main_char_id ) {
                        $main_char_name = get_user_meta( $selected_user_id, 'esp_main_eve_character_name', true ); $main_total_sp = get_user_meta( $selected_user_id, 'esp_main_total_sp', true ); $main_last_updated = get_user_meta( $selected_user_id, 'esp_main_skills_last_updated', true );
                        echo '<li class="char-item main-char-item">'; echo '<strong>MAIN:</strong> ' . esc_html( $main_char_name ) . ' (ID: ' . esc_html( $main_char_id ) . ')';
                        echo '<div class="char-meta">'; echo 'Total SP: ' . esc_html( number_format( (float) $main_total_sp ) ); if ($main_last_updated) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$main_last_updated)); echo '</div>';
                        echo '<div class="char-actions"><a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id, 'view_char_id' => $main_char_id], admin_url('admin.php'))) . '">' . esc_html__('View Skills', 'eve-skill-plugin') . '</a>';
                        
                        if (current_user_can('manage_options') && empty($alt_characters)) {
                            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="assign-alt-form" style="margin-top: 5px;">';
                            echo '<input type="hidden" name="action" value="esp_admin_reassign_character">';
                            echo '<input type="hidden" name="original_wp_user_id" value="'.esc_attr($selected_user_id).'">';
                            echo '<input type="hidden" name="character_id_to_move" value="'.esc_attr($main_char_id).'">';
                            echo '<input type="hidden" name="character_type_to_move" value="main">';
                            wp_nonce_field('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce');
                            $select_id = 'reassign_main_to_user_'.esc_attr($selected_user_id).'_'.esc_attr($main_char_id);
                            echo '<label for="'.esc_attr($select_id).'" class="screen-reader-text">' . esc_html__('Assign this Main to different User as Alt:', 'eve-skill-plugin') . '</label>';
                            echo '<select name="new_main_wp_user_id" id="'.esc_attr($select_id).'">';
                            echo '<option value="">' . esc_html__('-- Select Target User --', 'eve-skill-plugin') . '</option>';
                            $all_potential_main_users_args = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all_with_meta', 'exclude' => [$selected_user_id] ];
                            $potential_main_users = get_users($all_potential_main_users_args);
                            foreach ($potential_main_users as $potential_user) {
                                $potential_main_char_name = get_user_meta($potential_user->ID, 'esp_main_eve_character_name', true);
                                if ($potential_main_char_name) {
                                    echo '<option value="'.esc_attr($potential_user->ID).'">';
                                    echo esc_html($potential_user->display_name . ' (' . $potential_main_char_name . ')');
                                    echo '</option>';
                                }
                            }
                            echo '</select>';
                            submit_button(__('Assign as Alt', 'eve-skill-plugin'), 'secondary small admin-action-button', 'reassign_main', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to re-assign this main character as an alt to the selected user? This user will then have no main character.', 'eve-skill-plugin')).'");']);
                            echo '</form>';
                        }
                         echo '</div>'; 

                        if (is_array($alt_characters) && !empty($alt_characters)) {
                            echo '<div class="alt-list-heading">' . esc_html__('ALTS:', 'eve-skill-plugin') . '</div>'; echo '<ul>'; 
                            foreach ($alt_characters as $alt_char_idx => $alt_char) {
                                 if (!is_array($alt_char) || !isset($alt_char['id']) || !isset($alt_char['name'])) continue; 
                                echo '<li class="char-item">'; echo esc_html( $alt_char['name'] ) . ' (ID: ' . esc_html( $alt_char['id'] ) . ')';
                                echo '<div class="char-meta">'; echo 'Total SP: ' . esc_html( number_format( (float) ($alt_char['total_sp'] ?? 0) ) ); if (!empty($alt_char['skills_last_updated'])) echo ' | Last Updated: ' . esc_html(wp_date(get_option('date_format').' '.get_option('time_format'), (int)$alt_char['skills_last_updated'])); echo '</div>';
                                echo '<div class="char-actions">';
                                echo '<a href="' . esc_url(add_query_arg(['page' => 'eve_view_all_user_skills', 'view_user_id' => $selected_user_id, 'view_char_id' => $alt_char['id']], admin_url('admin.php'))) . '">' . esc_html__('View Skills', 'eve-skill-plugin') . '</a>';
                                if (current_user_can('manage_options')) {
                                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; echo '<input type="hidden" name="action" value="esp_admin_promote_alt_to_main">'; echo '<input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'">'; echo '<input type="hidden" name="alt_char_id_to_promote" value="'.esc_attr($alt_char['id']).'">'; wp_nonce_field('esp_admin_promote_alt_action', 'esp_admin_promote_alt_nonce'); submit_button(__('Promote to Main', 'eve-skill-plugin'), 'secondary small admin-action-button', 'promote_alt', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to promote this alt to main? The current main will become an alt.', 'eve-skill-plugin')).'");']); echo '</form>';
                                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; echo '<input type="hidden" name="action" value="esp_admin_remove_user_alt_character">'; echo '<input type="hidden" name="user_id_to_affect" value="'.esc_attr($selected_user_id).'">'; echo '<input type="hidden" name="alt_char_id_to_remove" value="'.esc_attr($alt_char['id']).'">'; wp_nonce_field('esp_admin_remove_alt_action', 'esp_admin_remove_alt_nonce'); submit_button(__('Remove Alt', 'eve-skill-plugin'), 'delete small admin-action-button', 'remove_alt', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to remove this alt from this user?', 'eve-skill-plugin')).'");']); echo '</form>';
                                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="assign-alt-form">'; echo '<input type="hidden" name="action" value="esp_admin_reassign_character">'; echo '<input type="hidden" name="original_wp_user_id" value="'.esc_attr($selected_user_id).'">'; echo '<input type="hidden" name="character_id_to_move" value="'.esc_attr($alt_char['id']).'">'; echo '<input type="hidden" name="character_type_to_move" value="alt">'; wp_nonce_field('esp_admin_reassign_char_action', 'esp_admin_reassign_char_nonce'); $select_alt_id = 'reassign_alt_to_user_'.esc_attr($selected_user_id).'_'.esc_attr($alt_char['id']); echo '<label for="'.esc_attr($select_alt_id).'" class="screen-reader-text">' . esc_html__('Assign Alt to different Main User:', 'eve-skill-plugin') . '</label>'; echo '<select name="new_main_wp_user_id" id="'.esc_attr($select_alt_id).'">'; echo '<option value="">' . esc_html__('-- Select Target User --', 'eve-skill-plugin') . '</option>'; $all_potential_main_users_args_alt = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all_with_meta', 'exclude' => [$selected_user_id] ]; $potential_main_users_alt = get_users($all_potential_main_users_args_alt); foreach ($potential_main_users_alt as $potential_user_alt) { $potential_main_char_name_alt = get_user_meta($potential_user_alt->ID, 'esp_main_eve_character_name', true); if ($potential_main_char_name_alt) { echo '<option value="'.esc_attr($potential_user_alt->ID).'">'; echo esc_html($potential_user_alt->display_name . ' (' . $potential_main_char_name_alt . ')'); echo '</option>'; } } echo '</select>'; submit_button(__('Assign Alt', 'eve-skill-plugin'), 'secondary small admin-action-button', 'reassign_alt', false, ['onclick' => 'return confirm("'.esc_js(__('Are you sure you want to re-assign this alt to the selected main character\'s account?', 'eve-skill-plugin')).'");']); echo '</form>';
                                } echo '</div>'; echo '</li>';
                            } echo '</ul>'; 
                        } else { echo '<p class="char-meta">' . esc_html__('No alt characters linked.', 'eve-skill-plugin') . '</p>'; }
                        echo '</li>'; 
                    } else { echo '<li>' . esc_html__( 'No main EVE character linked for this user.', 'eve-skill-plugin' ) . '</li>'; }
                    echo '</ul>'; 
                    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eve_view_all_user_skills' ) ) . '">« ' . esc_html__( 'Back to all users list', 'eve-skill-plugin' ) . '</a></p>';
                }
            } else { 
                $args = [ 'meta_key' => 'esp_main_eve_character_id', 'fields' => 'all', 'orderby' => 'display_name', ]; $users_with_main_eve = get_users( $args );
                if ( ! empty( $users_with_main_eve ) ) {
                    echo '<table class="wp-list-table widefat fixed striped">'; echo '<thead><tr><th>' . esc_html__( 'WordPress User', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Main EVE Character', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Alts Count', 'eve-skill-plugin' ) . '</th><th>' . esc_html__( 'Action', 'eve-skill-plugin' ) . '</th></tr></thead>'; echo '<tbody>';
                    foreach ( $users_with_main_eve as $user ) {
                        $main_char_name = get_user_meta( $user->ID, 'esp_main_eve_character_name', true ); $alts = get_user_meta($user->ID, 'esp_alt_characters', true); $alts_count = is_array($alts) ? count($alts) : 0; $view_link = add_query_arg( ['page' => 'eve_view_all_user_skills', 'view_user_id' => $user->ID ], admin_url( 'admin.php' ) );
                        echo '<tr>'; echo '<td>' . esc_html( $user->display_name ) . ' (' . esc_html($user->user_login) . ')</td>'; echo '<td>' . esc_html( $main_char_name ) . '</td>'; echo '<td>' . esc_html( $alts_count ) . '</td>'; echo '<td><a href="' . esc_url( $view_link ) . '">' . esc_html__( 'View Details', 'eve-skill-plugin' ) . '</a></td>'; echo '</tr>';
                    } echo '</tbody></table>';
                } else { echo '<p>' . esc_html__( 'No users have linked their main EVE character yet.', 'eve-skill-plugin' ) . '</p>'; }
            } ?>
        </div> <?php
    }

    public function display_character_skills_for_admin( $user_id_to_view, $character_id_to_display ) {
        $main_char_id = get_user_meta($user_id_to_view, 'esp_main_eve_character_id', true);
        $is_main = ($character_id_to_display == $main_char_id);
        $skills_data  = null; $total_sp = 0; $last_updated = null;

        if ($is_main) {
            $skills_data  = get_user_meta( $user_id_to_view, 'esp_main_skills_data', true );
            $total_sp     = get_user_meta( $user_id_to_view, 'esp_main_total_sp', true );
            $last_updated = get_user_meta( $user_id_to_view, 'esp_main_skills_last_updated', true);
        } else { 
            $skills_data  = ESP_Helpers::get_alt_character_data_item($user_id_to_view, $character_id_to_display, 'skills_data');
            $total_sp     = ESP_Helpers::get_alt_character_data_item($user_id_to_view, $character_id_to_display, 'total_sp');
            $last_updated = ESP_Helpers::get_alt_character_data_item($user_id_to_view, $character_id_to_display, 'skills_last_updated');
        }
        if ($last_updated) { echo '<p><small>' . sprintf(esc_html__('Skills last updated: %s', 'eve-skill-plugin'), esc_html(wp_date( get_option('date_format') . ' ' . get_option('time_format'), (int)$last_updated))) . '</small></p>';
        } else { echo '<p><small>' . esc_html__('Skills last updated: Unknown', 'eve-skill-plugin') . '</small></p>'; }

        if ( $skills_data && is_array( $skills_data ) && !empty($skills_data) ) {
            echo '<p>' . sprintf( esc_html__( 'Total Skillpoints: %s', 'eve-skill-plugin' ), number_format( (float) $total_sp ) ) . '</p>';
            echo '<table class="wp-list-table widefat striped"><thead><tr><th>Skill Name</th><th>Skill ID</th><th>Level</th><th>Skillpoints</th></tr></thead><tbody>';
            $skill_details_for_sort = [];
            foreach ( $skills_data as $skill_key => $skill ) {
                if ( !is_array($skill) || !isset($skill['skill_id']) || !isset($skill['active_skill_level']) || !isset($skill['skillpoints_in_skill']) ) {
                    error_log("[EVE Skill Plugin] Malformed skill entry for user $user_id_to_view, char $character_id_to_display. Skill key: $skill_key. Data: " . print_r($skill, true)); continue; 
                }
                $skill_details_for_sort[] = [ 'name' => ESP_Helpers::get_skill_name( (int)$skill['skill_id'] ), 'id' => (int)$skill['skill_id'], 'level' => (int)$skill['active_skill_level'], 'sp' => (float)$skill['skillpoints_in_skill'] ];
            }
            if (!empty($skill_details_for_sort)) {
                usort($skill_details_for_sort, function($a, $b) { return strcmp($a['name'], $b['name']); });
                foreach ( $skill_details_for_sort as $skill_detail ) { printf( '<tr><td>%s</td><td>%d</td><td>%d</td><td>%s</td></tr>', esc_html( $skill_detail['name'] ), esc_html( $skill_detail['id'] ), esc_html( $skill_detail['level'] ), esc_html( number_format( $skill_detail['sp'] ) ) ); }
            } else if (is_array($skills_data) && empty($skill_details_for_sort) && !empty($skills_data) ) {
                 echo '<tr><td colspan="4">' . esc_html__('Skill data appears to be malformed or empty.', 'eve-skill-plugin') . '</td></tr>';
            } else if (!is_array($skills_data)) { 
                 echo '<tr><td colspan="4">' . esc_html__('Skill data is not in the expected format.', 'eve-skill-plugin') . '</td></tr>';
            }
            echo '</tbody></table>';
        } else { 
            echo '<p>' . esc_html__( 'No skill data found for this character, or skills have not been fetched/stored correctly.', 'eve-skill-plugin' ) . '</p>';
            if (is_array($skills_data) && empty($skills_data)) { echo '<p><small>' . esc_html__('(The skill list from ESI was empty).', 'eve-skill-plugin') . '</small></p>'; }
        }
    }
}