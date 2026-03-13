<?php

namespace App;

// Check whether WordPress and ACF are available; bail if not.
if (! function_exists('acf_register_block_type')) {
    return;
}
if (! function_exists('add_filter')) {
    return;
}
if (! function_exists('add_action')) {
    return;
}

// Add the default blocks location, 'views/blocks', via filter
add_filter('sage-acf-gutenberg-blocks-templates', function () {
    return ['views/blocks'];
});

/**
 * Create blocks based on templates found in Sage's "views/blocks" directory
 */
add_action('acf/init', function () {

    // Global $sage_error so we can throw errors in the typical sage manner
    global $sage_error;

    // Get an array of directories containing blocks
    $directories = apply_filters('sage-acf-gutenberg-blocks-templates', []);

    // Check whether ACF exists before continuing
    foreach ($directories as $directory) {
        $dir = isSage10() ? \Roots\resource_path($directory) : \locate_template($directory);

        // Sanity check whether the directory we're iterating over exists first
        if (!file_exists($dir)) {
            return;
        }

        // Iterate over the directories provided and look for templates
        $template_directory = new \DirectoryIterator($dir);

        foreach ($template_directory as $template) {
            if (!$template->isDot() && !$template->isDir()) {

                // Strip the file extension to get the slug
                $slug = removeBladeExtension($template->getFilename());
                // If there is no slug (most likely because the filename does
                // not end with ".blade.php", move on to the next file.
                if (!$slug) {
                    continue;
                }

                // Get header info from the found template file(s)
                $file = $dir . '/' . $slug . '.blade.php';
                $file_path = file_exists($file) ? $file : '';
                $file_headers = get_file_data($file_path, [
                    'title' => 'Title',
                    'description' => 'Description',
                    'category' => 'Category',
                    'icon' => 'Icon',
                    'keywords' => 'Keywords',
                    'mode' => 'Mode',
                    'align' => 'Align',
                    'post_types' => 'PostTypes',
                    'supports_align' => 'SupportsAlign',
                    'supports_anchor' => 'SupportsAnchor',
                    'supports_mode' => 'SupportsMode',
                    'supports_jsx' => 'SupportsInnerBlocks',
                    'supports_color_text' => 'SupportsColorText',
                    'supports_color_background' => 'SupportsColorBackground',
                    'supports_align_text' => 'SupportsAlignText',
                    'supports_align_content' => 'SupportsAlignContent',
                    'supports_multiple' => 'SupportsMultiple',
                    'enqueue_style'     => 'EnqueueStyle',
                    'enqueue_script'    => 'EnqueueScript',
                    'enqueue_assets'    => 'EnqueueAssets',
                ]);

                if (empty($file_headers['title'])) {
                    $sage_error(__('This block needs a title: ' . $dir . '/' . $template->getFilename(), 'sage'), __('Block title missing', 'sage'));
                }

                if (empty($file_headers['category'])) {
                    $sage_error(__('This block needs a category: ' . $dir . '/' . $template->getFilename(), 'sage'), __('Block category missing', 'sage'));
                }

                // Checks if dist contains this asset, then enqueues the dist version.
                if (!empty($file_headers['enqueue_style'])) {
                    checkAssetPath($file_headers['enqueue_style']);
                }

                if (!empty($file_headers['enqueue_script'])) {
                    checkAssetPath($file_headers['enqueue_script']);
                }

                // Set up block data for registration
                $data = [
                    'name' => $slug,
                    'title' => $file_headers['title'],
                    'description' => $file_headers['description'],
                    'category' => $file_headers['category'],
                    'icon' => $file_headers['icon'],
                    'keywords' => explode(' ', $file_headers['keywords']),
                    'mode' => $file_headers['mode'],
                    'align' => $file_headers['align'],
                    'render_callback'  => __NAMESPACE__.'\\sage_blocks_callback',
                    'enqueue_style'   => $file_headers['enqueue_style'],
                    'enqueue_script'  => $file_headers['enqueue_script'],
                    'enqueue_assets'  => $file_headers['enqueue_assets'],
                ];

                // If the PostTypes header is set in the template, restrict this block to those types
                if (!empty($file_headers['post_types'])) {
                    $data['post_types'] = explode(' ', $file_headers['post_types']);
                }

                // If the SupportsAlign header is set in the template, restrict this block to those aligns
                if (!empty($file_headers['supports_align'])) {
                    $data['supports']['align'] = in_array($file_headers['supports_align'], array('true', 'false'), true) ? filter_var($file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN) : explode(' ', $file_headers['supports_align']);
                }

                // If the SupportsMode header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_anchor'])) {
                    $data['supports']['anchor'] = $file_headers['supports_anchor'] === 'true' ? true : false;
                }

                // If the SupportsMode header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_mode'])) {
                    $data['supports']['mode'] = $file_headers['supports_mode'] === 'true' ? true : false;
                }

                // If the SupportsInnerBlocks header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_jsx'])) {
                   $data['supports']['jsx'] = $file_headers['supports_jsx'] === 'true' ? true : false;
                }

                // If the SupportsColorText header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_color_text'])) {
                    $data['supports']['color']['text'] = $file_headers['supports_color_text'] === 'true' ? true : false;
                }

                // If the SupportsColorBackground header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_color_background'])) {
                    $data['supports']['color']['background'] = $file_headers['supports_color_background'] === 'true' ? true : false;
                }

                // If the SupportsAlignText header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_align_text'])) {
                   $data['supports']['align_text'] = $file_headers['supports_align_text'] === 'true' ? true : false;
                }

                // If the SupportsAlignContent header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_align_text'])) {
                   $data['supports']['align_content'] = $file_headers['supports_align_content'] === 'true' ? true : false;
                }

                // If the SupportsMultiple header is set in the template, restrict this block multiple feature
                if (!empty($file_headers['supports_multiple'])) {
                    $data['supports']['multiple'] = $file_headers['supports_multiple'] === 'true' ? true : false;
                }

                // Register the block with ACF
                \acf_register_block_type( apply_filters( "sage/blocks/$slug/register-data", $data ) );
            }
        }
    }
});

/**
 * Callback to register blocks
 */
function sage_blocks_callback($block, $content = '', $is_preview = false, $post_id = 0)
{
    // Set up the slug to be useful
    $slug  = str_replace('acf/', '', $block['name']);
    $block = array_merge(['className' => ''], $block);

    // Maybe hide according to metadata
    if (isset($block['metadata']['blockVisibility']) && false === $block['metadata']['blockVisibility']) {
        return;
    }

    // Set up the block data
    $block['post_id'] = $post_id;
    $block['is_preview'] = $is_preview;
    $block['content'] = $content;
    $block['slug'] = $slug;
    $block['anchor'] = isset($block['anchor']) ? $block['anchor'] : '';
    // Send classes as array to filter for easy manipulation.
    $block['classes'] = [
        $slug,
        $block['className'],
        $block['is_preview'] ? 'is-preview' : null,
        'align'.$block['align']
    ];

    $block_type = function_exists('acf_get_block_type') ? \acf_get_block_type($block['name']) : null;

    if (support_color_feature($block_type, 'background')) {
        if ($background_slug = resolve_block_color_slug($block, 'background')) {
            $block['backgroundColor'] = $background_slug;
        }
    }

    if (support_color_feature($block_type, 'text')) {
        if ($text_slug = resolve_block_color_slug($block, 'text')) {
            $block['textColor'] = $text_slug;
        }
    }

    // Filter the block data.
    $block = apply_filters("sage/blocks/$slug/data", $block);

    // Join up the classes.
    $block['classes'] = implode(' ', array_filter($block['classes']));

    // Get the template directories.
    $directories = apply_filters('sage-acf-gutenberg-blocks-templates', []);

    foreach ($directories as $directory) {
        $view = ltrim($directory, 'views/') . '/' . $slug;

        if (isSage10()) {

            if (\Roots\view()->exists($view)) {
                // Use Sage's view() function to echo the block and populate it with data
                echo \Roots\view($view, ['block' => $block]);
            }

        } else {
            try {
                // Use Sage 9's template() function to echo the block and populate it with data
                echo \App\template($view, ['block' => $block]);
            } catch (\Exception $e) {
                //
            }
        }
    }
}

/**
 * Function to strip the `.blade.php` from a blade filename
 */
function removeBladeExtension($filename)
{
    // Filename must end with ".blade.php". Parenthetical captures the slug.
    $blade_pattern = '/(.*)\.blade\.php$/';
    $matches = [];
    // If the filename matches the pattern, return the slug.
    if (preg_match($blade_pattern, $filename, $matches)) {
        return $matches[1];
    }
    // Return FALSE if the filename doesn't match the pattern.
    return FALSE;
}

/**
 * Checks asset path for specified asset.
 *
 * @param string &$path
 *
 * @return void
 */
function checkAssetPath(&$path)
{
    if (preg_match("/^(styles|scripts)/", $path)) {
        $path = isSage10() ? \Roots\asset($path)->uri() : \App\asset_path($path);
    }
}

/**
 * Check if Sage 10 is used.
 *
 * @return bool
 */
function isSage10()
{
    return class_exists('Roots\Acorn\Application');
}

function support_color_feature($block_type, $feature)
{
    if (!is_array($block_type) || empty($block_type['supports']['color'])) {
        return false;
    }

    if ($feature === 'background') {
        return !empty($block_type['supports']['color']['background']);
    }

    if ($feature === 'text') {
        return !empty($block_type['supports']['color']['text']);
    }

    return false;
}

function resolve_block_color_slug(array $block, $context)
{
    $slug_key = $context === 'background' ? 'backgroundColor' : 'textColor';
    $style_key = $context === 'background' ? 'background' : 'text';

    $candidates = [];

    if (!empty($block[$slug_key]) && is_string($block[$slug_key])) {
        $candidates[] = $block[$slug_key];
    }

    if (!empty($block['data'][$slug_key]) && is_string($block['data'][$slug_key])) {
        $candidates[] = $block['data'][$slug_key];
    }

    if (!empty($block['attrs'][$slug_key]) && is_string($block['attrs'][$slug_key])) {
        $candidates[] = $block['attrs'][$slug_key];
    }

    foreach ($candidates as $candidate) {
        $candidate = sanitize_color_slug($candidate);
        if (!empty($candidate)) {
            return $candidate;
        }
    }

    $class_sources = [];

    if (!empty($block['className'])) {
        $class_sources[] = $block['className'];
    }

    if (!empty($block['classes'])) {
        $class_sources[] = is_array($block['classes']) ? implode(' ', array_filter($block['classes'])) : $block['classes'];
    }

    foreach ($class_sources as $class_string) {
        $pattern = $context === 'background' ? '/has-([a-z0-9-]+)-background-color/' : '/has-([a-z0-9-]+)-color/';
        if (preg_match($pattern, $class_string, $matches)) {
            return sanitize_color_slug($matches[1]);
        }
    }

    $style_candidates = [];

    if (!empty($block['attrs']['style']['color'][$style_key])) {
        $style_candidates[] = $block['attrs']['style']['color'][$style_key];
    }

    foreach ($style_candidates as $style_value) {
        if (!is_string($style_value)) {
            continue;
        }

        if (strpos($style_value, 'var(') !== false && preg_match('/--wp--preset--color--([a-z0-9-]+)/', $style_value, $matches)) {
            return sanitize_color_slug($matches[1]);
        }

        if ($slug = match_palette_color($style_value)) {
            return $slug;
        }
    }

    return null;
}

function sanitize_color_slug($value)
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    if (!preg_match('/^[a-z0-9-]+$/i', $value)) {
        return null;
    }

    return \sanitize_key($value);
}

function match_palette_color($value)
{
    $value = strtolower(trim($value));

    if ($value === '') {
        return null;
    }

    $palette = \get_theme_support('editor-color-palette');

    if (empty($palette)) {
        return null;
    }

    if (isset($palette[0]) && is_array($palette[0])) {
        $palette = $palette[0];
    }

    foreach ($palette as $color) {
        if (empty($color['slug']) || empty($color['color'])) {
            continue;
        }

        if (strtolower($color['color']) === $value) {
            return \sanitize_key($color['slug']);
        }
    }

    return null;
}
