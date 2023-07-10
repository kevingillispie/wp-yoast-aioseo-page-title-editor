<?php

namespace SchemaScalpel;

if (!defined('ABSPATH')) :
    exit();
endif;

/**
 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 'bar' ), array( 'ID' => 1 ) )
 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
 * _yoast_wpseo_title
 * _aioseo_title
 * aioseo_posts
 */

// if (isset($_GET["update"])) :
//     $custom_schema = sanitize_text_field($_GET["schema"]);
//     $wpdb->update($custom_schemas_table, array("custom_schema" => serialize($custom_schema)), array("id" => sanitize_text_field($_GET["update"])));
// endif;

if (!empty($_GET['seo_title'])) :
    global $wpdb;
    $seo_type = ($_GET['seo_type'] == 'Yoast') ? '_yoast_wpseo_title' : '_aioseo_title';
    $wpdb->update($wpdb->prefix . 'postmeta', array('meta_value' => sanitize_text_field($_GET['seo_title'])), array('post_id' => sanitize_key($_GET['post_id']), 'meta_key'=>$seo_type), array('%s'), array('%d', '%s'));

    if ($_GET['seo_type'] == 'Yoast') {
        // yoast_indexable
        $wpdb->update($wpdb->prefix . 'yoast_indexable', array('title' => sanitize_text_field($_GET['seo_title'])), array('object_id' => sanitize_key($_GET['post_id'])), array('%s'), array('%d'));
    }
    
    if ($_GET['seo_type'] == 'AIOSEO') {
        // aioseo_posts
        $wpdb->update($wpdb->prefix . 'aioseo_posts', array('title' => sanitize_text_field($_GET['seo_title'])), array('post_id' => sanitize_key($_GET['post_id'])), array('%s'), array('%d'));
    }

    exit;
endif;

if (!empty($_GET['wp_title'])) :
    global $wpdb;
    $wpdb->update($wpdb->prefix . 'posts', array('column' => 'post_title', 'field' => sanitize_text_field($_GET['wp_title'])), array('ID' => sanitize_key($_GET['post_id'])), array('%s', '%s'), array('%d'));
    exit;
endif;


$is_yoast_installed = false;
foreach (get_plugins() as $key => $value) :
    if (stripos($value['TextDomain'], 'wordpress-seo') > -1) :
        $is_yoast_installed = true;
        break;
    endif;
endforeach;

$is_aio_installed = false;
foreach (get_plugins() as $key => $value) :
    if (stripos($value['TextDomain'], 'all-in-one-seo-pack') > -1) :
        $is_aio_installed = true;
        break;
    endif;
endforeach;

$seo_plugin_name = null;
if ($is_yoast_installed === true) {
    $seo_plugin_name = 'Yoast';
} else if ($is_aio_installed === true) {
    $seo_plugin_name = 'AIOSEO';
}

// _yoast_wpseo_title
// _aioseo_title

/**
 * ID
 * post_author
 * post_date
 * post_date_gmt
 * post_content
 * post_title
 * post_excerpt
 * post_status
 * comment_status
 * ping_status
 * post_password
 * post_name ... slug
 * to_ping
 * pinged
 * post_modified
 * post_modified_gmt
 * post_content_filtered
 * post_parent
 * guid
 * menu_order
 * post_type
 * post_mime_type
 * comment_count
 * filter
 * creation_timestamp
 */

$all_pages = get_pages(['post_type' => 'page']);
$all_posts = get_posts(['numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);

$page_count = count($all_pages);
$post_count = count($all_posts);

function get_yoast_title($ID)
{
    global $wpdb;
    $query = $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id=%d AND meta_key=%s;", $ID, '_yoast_wpseo_title');
    $results = $wpdb->get_results($query, ARRAY_A);
    return (is_array($results) && !empty($results[0]['meta_value'])) ? $results[0]['meta_value'] : null;
}

function get_aio_title($ID)
{
    global $wpdb;
    $query = $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id=%d AND meta_key=%s;", $ID, '_aioseo_title');
    $results = $wpdb->get_results($query, ARRAY_A);
    return (is_array($results) && !empty($results[0]['meta_value'])) ? $results[0]['meta_value'] : null;
}

function get_duplicate_records($ID)
{
    global $wpdb;
    $query = $wpdb->prepare("SELECT  *
    FROM    {$wpdb->prefix}postmeta ptm1
    WHERE   EXISTS
            (
            SELECT  1
            FROM    wpri_postmeta ptm2
            WHERE   ptm2.meta_key=ptm1.meta_key AND ptm2.meta_key=%s
            LIMIT 1, 1
            )
            AND post_id={$ID}
    ORDER BY post_id
    DESC;", '_yoast_wpseo_title', $ID);
    $results = $wpdb->get_results($query, ARRAY_A);
}

/*

SELECT  *
FROM    wpri_postmeta mto
WHERE   EXISTS
        (
        SELECT  1
        FROM    wpri_postmeta mti
        WHERE   mti.meta_key = mto.meta_key AND mti.meta_key='_yoast_wpseo_title'
        LIMIT 1, 1
        )
ORDER BY post_id

DELETE t1 FROM wpri_postmeta t1
INNER JOIN wpri_postmeta t2 
WHERE 
    t1.meta_id < t2.meta_id
    AND 
    t1.meta_key = t2.meta_key
    AND
    t2.meta_key='_yoast_wpseo_title';

*/

?>
<style>
    .small-col {
        max-width: 65px
    }
</style>
<main class="container mt-5 ms-0">
    <header>
        <h1>Title Editor <img src="<?php echo plugin_dir_url(SCHEMA_SCALPEL_PLUGIN); ?>admin/images/scalpel_title.svg" class="mt-n4" /></h1>
        <p class="alert alert-primary" role="alert">The default settings for this plugin allow it to perform at its best. However, you may encounter an edge case that requires modification of the default settings.</p>
        <p class="alert alert-warning mt-4" role="alert">NOTE: <em>This plugin <a href="/wp-admin/admin.php?page=scsc_settings#disable_yoast_schema">automatically overrides Yoast's and AIOSEO's schema</a> and injects your customized schema to better fit your SEO objectives.</em></p>
    </header>
    <h2>Page Titles</h2>
    <form>
        <input type="hidden" name="page" value="scsc_bulk" />
        <input type="hidden" name="save" value="save" />
        <table class="table table-secondary table-striped">
            <thead>
                <tr>
                    <th scope="col">Length, WP</th>
                    <th scope="col">Default Title</th>
                    <th scope="col">Length, SEO</th>
                    <th scope="col"><?= $seo_plugin_name; ?> Title<sup>1</sup></th>
                    <th scope="col">Reset Changes</th>
                    <th scope="col">Save Changes</th>
                </tr>
            </thead>
            <tbody>
                <?php

                foreach ($all_pages as $key => $value) :
                    $seo_title = null;
                    $editable = null;
                    $wp_title_length = strlen($value->post_title);

                    if ($is_yoast_installed === true) {
                        $seo_title = get_yoast_title($value->ID);
                    } else if ($is_aio_installed === true) {
                        $seo_title = get_aio_title($value->ID);
                    }

                    $seo_title_length = strlen($seo_title);
                    $seo_title_too_long = ($seo_title_length > 60) ? ' class="text-danger"' : '';

                    if (!empty($seo_title)) {
                        $editable = 'contenteditable';
                    }

                    echo <<<TR
                    <tr>
                        <td>{$wp_title_length}</td>
                        <td data-wp-title="" data-original-title="{$value->post_title}" data-id="{$value->ID}" contenteditable>{$value->post_title}</td>
                        <td><span{$seo_title_too_long}>{$seo_title_length}</span></td>
                        <td data-seo-title="" data-original-title="{$seo_title}" data-id="{$value->ID}" {$editable}>{$seo_title}</td>
                        <td><button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetTitleChanges({$value->ID})" data-id="{$value->ID}" disabled>Reset</button></td>
                        <td><button type="button" class="btn btn-primary btn-sm w-100" data-id="{$value->ID}" onclick="saveTitleSingle({$value->ID})" disabled>Save</button></td>
                    </tr>
                    TR;
                endforeach;

                ?>
            </tbody>
        </table>
        <div><small><sup>1</sup> <i>If this column is blank for one or all of your pages, you have either a) never installed Yoast or b) installed it and did not modify the default title.</i></small></div>
    </form>
</main>
<script>
    <?php

    /**
     * LOAD ORDER MATTERS!
     */
    // include_once(SCHEMA_SCALPEL_DIRECTORY . '/admin/js/schema-scalpel-user-settings.js');
    include_once(SCHEMA_SCALPEL_DIRECTORY . '/admin/js/prism.js');

    ?>
</script>
<script>
    var contentEditable = document.querySelectorAll('[contenteditable]');
    contentEditable.forEach(textElement => {
        textElement.addEventListener('keyup', () => {
            toggleTableRowState(textElement);
        });
    });

    function toggleTableRowState(textElement) {
        let btn = document.querySelectorAll('button[data-id="' + textElement.dataset.id + '"]');
        if (btn && textElement.innerText != textElement.dataset.originalTitle) {
            textElement.classList.add('text-danger');
            btn.forEach(b => {
                b.removeAttribute('disabled');
            });
        } else if (btn) {
            textElement.classList.remove('text-danger');
            btn.forEach(b => {
                b.setAttribute('disabled', '');
            });
        }
    }

    function resetTitleChanges(id) {
        let allFields = document.querySelectorAll('[data-id="' + id + '"]:not(button)');
        allFields.forEach(field => {
            field.innerText = field.dataset.originalTitle;
            toggleTableRowState(field);
        })
    }

    function updateWordPressTitle(id, updatedTitle) {
        let request = new XMLHttpRequest();
        request.onreadystatechange = () => {
            if (request.readyState == 4) alert('WordPress page title has been updated.');
        }
        request.open("GET", '/wp-admin/admin.php?page=scsc_title_editor&post_id=' + id + '&wp_title=' + updatedTitle);
        request.send();
    }

    function updateSeoTitle(id, updatedTitle, seoType) {
        let request = new XMLHttpRequest();
        request.onreadystatechange = () => {
            if (request.readyState == 4) alert('SEO page title has been updated.');
        }
        request.open("GET", '/wp-admin/admin.php?page=scsc_title_editor&post_id=' + id + '&seo_type=' + seoType + '&seo_title=' + updatedTitle);
        request.send();
    }

    function saveTitleSingle(id) {
        let wpTitleElement = document.querySelector('[data-wp-title][data-id="' + id + '"]');
        let seoTitleElement = document.querySelector('[data-seo-title][data-id="' + id + '"]');
        if (wpTitleElement.dataset.originalTitle != wpTitleElement.innerText) {
            updateWordPressTitle(id, wpTitleElement.innerText);
        }
        if (seoTitleElement.dataset.originalTitle != seoTitleElement.innerText) {
            updateSeoTitle(id, seoTitleElement.innerText, '<?= $seo_plugin_name; ?>');
        }
    }
</script>
