<?php

add_action('admin_menu', 'redirecionamento_add_settings_page');
add_action('admin_init', 'redirecionamento_register_settings');

function redirecionamento_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=redirecionamento',
        'Configurações de Taxas',
        'Configurações de Taxas',
        'manage_woocommerce',
        'redirecionamento-taxas',
        'redirecionamento_settings_page'
    );
}

function redirecionamento_register_settings() {
    register_setting('redirecionamento_taxes_group', 'redirecionamento_taxes', 'sanitize_redirecionamento_taxes');
    add_settings_section('redirecionamento_section', 'Taxas de Redirecionamento', 'redirecionamento_section_callback', 'redirecionamento-taxes');
    add_settings_field('redirecionamento_field', '', 'redirecionamento_field_callback', 'redirecionamento-taxes', 'redirecionamento_section');

    register_setting('servico_taxes_group', 'servico_taxes', 'sanitize_servico_taxes');
    add_settings_section('servico_section', 'Taxas de Serviço', 'servico_section_callback', 'servico-taxes');
    add_settings_field('servico_field', '', 'servico_field_callback', 'servico-taxes', 'servico_section');

    register_setting('frete_taxes_group', 'frete_taxes', 'sanitize_frete_taxes');
    add_settings_section('frete_section', 'Taxas de Frete', 'frete_section_callback', 'frete-taxes');
    add_settings_field('frete_field', '', 'frete_field_callback', 'frete-taxes', 'frete_section');

    register_setting('seguro_taxes_group', 'seguro_taxes', 'sanitize_seguro_taxes');
    add_settings_section('seguro_section', 'Taxa de Seguro', 'seguro_section_callback', 'seguro-taxes');
    add_settings_field('seguro_field', '', 'seguro_field_callback', 'seguro-taxes', 'seguro_section');

    register_setting('multa_taxes_group', 'multa_taxes', 'sanitize_multa_taxes');
    add_settings_section('multa_section', 'Taxa de Armazenamento', 'multa_section_callback', 'multa-taxes');
    add_settings_field('multa_field', '', 'multa_field_callback', 'multa-taxes', 'multa_section');

    register_setting('embalagem_taxes_group', 'embalagem_taxes', 'sanitize_embalagem_taxes');
    add_settings_section('embalagem_section', 'Taxas de Embalagem', 'embalagem_section_callback', 'embalagem-taxes');
    add_settings_field('embalagem_field', '', 'embalagem_field_callback', 'embalagem-taxes', 'embalagem_section');

    register_setting('fees_apply_to_categories_group', 'fees_apply_to_categories', 'sanitize_fees_apply_to');
    add_settings_section('fees_apply_to_section', 'Aplicar Taxas a Categorias', 'fees_apply_to_section_callback', 'fees-apply-to');
    add_settings_field('fees_apply_to_categories', 'Selecione Categorias', 'fees_apply_to_table_callback', 'fees-apply-to', 'fees_apply_to_section');
}

function redirecionamento_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="#redirecionamento" class="nav-tab nav-tab-active">Redirecionamento</a>
            <a href="#servico" class="nav-tab">Serviço</a>
            <a href="#frete" class="nav-tab">Frete</a>
            <a href="#seguro" class="nav-tab">Seguro</a>
            <a href="#multa" class="nav-tab">Armazenamento</a>
            <a href="#embalagem" class="nav-tab">Embalagem</a>
            <a href="#fees-apply-to" class="nav-tab">Aplicar Taxas</a>
        </h2>
        <div id="redirecionamento" class="tab-content">
            <form method="post" action="options.php">
                <?php
                settings_fields('redirecionamento_taxes_group');
                do_settings_sections('redirecionamento-taxes');
                submit_button();
                ?>
            </form>
        </div>
        <div id="servico" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php
                settings_fields('servico_taxes_group');
                do_settings_sections('servico-taxes');
                submit_button();
                ?>
            </form>
        </div>
        <div id="frete" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php
                settings_fields('frete_taxes_group');
                do_settings_sections('frete-taxes');
                submit_button();
                ?>
            </form>
        </div>
        <div id="seguro" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php
                settings_fields('seguro_taxes_group');
                do_settings_sections('seguro-taxes');
                submit_button();
                ?>
            </form>
        </div>
        <div id="multa" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php
                settings_fields('multa_taxes_group');
                do_settings_sections('multa-taxes');
                submit_button();
                ?>
            </form>
        </div>
        <div id="embalagem" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php
                settings_fields('embalagem_taxes_group');
                do_settings_sections('embalagem-taxes');
                submit_button();
                ?>
            </form>
        </div>
        <div id="fees-apply-to" class="tab-content" style="display:none;">
            <form method="post" action="options.php">
                <?php
                settings_fields('fees_apply_to_categories_group');
                do_settings_sections('fees-apply-to');
                submit_button();
                ?>
            </form>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
        });
    </script>
    <?php
}
?>
