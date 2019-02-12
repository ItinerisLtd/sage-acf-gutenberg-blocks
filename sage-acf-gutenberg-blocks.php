<?php

declare(strict_types=1);

namespace App;

/**
 * Render block blade templates
 */
function render_block(array $block): void
{
    // Set up the slug to be useful
    $slug = str_replace('acf/', '', $block['name']);

    // Set up the block data
    $block['slug'] = $slug;
    $block['classes'] = implode(' ', [$block['slug'], $block['className'], $block['align']]);
    $fields = \App\rename_block_keys($block['data'], $slug);

    // Use Sage's template() function to echo the block and populate it with data
    echo \App\template("blocks/${slug}", ['block' => $block, 'fields' => $fields]);
}

function rename_block_keys(array $fields, string $block_name, string $repeater_key = ''): array
{
    $return = [];
    $block_name = str_replace('-', '_', $block_name);
    foreach ($fields as $key => $value) {
        $remove = "field_{$block_name}_";

        if (substr($key, 0, strlen($remove)) == $remove) {
            $key = substr($key, strlen($remove));
        }

        if (is_array($value)) {
            $value = rename_block_keys($value, $block_name, key($value));
        }

        $return[$key] = $value;
    }
    return $return;
}

/**
* Register Blocks using templates found in "views/blocks"
*/
add_action('acf/init', function (): void {
    // Exit early if requirements not met
    if (! function_exists('\App\template') || ! function_exists('acf_register_block')) {
        return;
    }

    // Set the directory blocks are stored in
    $template_directory = "views/blocks/";

    // Set Sage9 friendly path at /theme-directory/resources/views/blocks
    $path = get_stylesheet_directory() . '/' . $template_directory;

    // If the directory doesn't exist, create it.
    if (!is_dir($path)) {
        mkdir($path);
    }

    // Global $sage_error so we can throw errors in the typical sage manner
    global $sage_error;

    // Get all templates in 'views/blocks'
    $dir = new \DirectoryIterator(\locate_template($template_directory));

    // Loop through found templates and set up data
    foreach ($dir as $fileinfo) {
        if ($fileinfo->isDot()) {
            return;
        }

        // Strip the file extension to get the slug
        $slug = str_replace('.blade.php', '', $fileinfo->getFilename());

        // Get header info from the found template file(s)
        $file_path = locate_template("views/blocks/${slug}.blade.php");
        $file_headers = get_file_data($file_path, [
            'title' => 'Title',
            'description' => 'Description',
            'category' => 'Category',
            'icon' => 'Icon',
            'keywords' => 'Keywords',
        ]);

        if (empty($file_headers['title'])) {
            $sage_error(
                __('This block needs a title: ' . $template_directory . $fileinfo->getFilename(), 'sage'),
                __('Block title missing', 'sage')
            );
        }

        if (empty($file_headers['category'])) {
            $sage_error(
                __('This block needs a category: ' . $template_directory . $fileinfo->getFilename(), 'sage'),
                __('Block category missing', 'sage')
            );
        }

        // Set up block data for registration
        $data = [
            'name' => $slug,
            'title' => $file_headers['title'],
            'description' => $file_headers['description'],
            'category' => $file_headers['category'],
            'icon' => $file_headers['icon'],
            'keywords' => explode(' ', $file_headers['keywords']),
            'render_callback'  => __NAMESPACE__.'\\render_block',
        ];

        // Register the block with ACF
        acf_register_block($data);
    }
});
