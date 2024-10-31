<?php
/*
Plugin Name: Orbisius Resume Organizer
Plugin URI: http://club.orbisius.com/products/wordpress-plugins/orbisius-resume-organizer/
Description: Orbisius Resume Organizer allows you to organize resumes sent from job applicants for later use.
Version: 1.0.2
Author: Svetoslav Marinov (Slavi)
Author URI: http://orbisius.com
*/

/*  Copyright 2012 Svetoslav Marinov (Slavi) <slavi@orbisius.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('ORBISIUS_RESUME_ORGANIZER_POST_TYPE', 'orb_resume');

// Set up plugin
add_action('init', 'orbisius_resume_organizer_init', 0);
add_action( 'init', 'orbisius_resume_organizer_handle_non_ui_calls', 100);

add_action('admin_init', 'orbisius_resume_organizer_admin_init');
add_action('admin_menu', 'orbisius_resume_organizer_setup_admin');
add_action('wp_footer', 'orbisius_resume_organizer_add_plugin_credits', 1000); // be the last in the footer

add_action('save_post', 'orbisius_resume_organizer_save_post', 10, 2);

add_action("wp_ajax_orbisius_resume_organizer_ajax_del_attachment", "orbisius_resume_organizer_ajax_del_attachment");
add_action("wp_ajax_nopriv_orbisius_resume_organizer_ajax_del_attachment", "orbisius_resume_organizer_ajax_not_auth");

register_activation_hook(__FILE__, 'orbisius_resume_organizer_on_activate');

/**
 *
 * @param type $post
 */
function orbisius_resume_organizer_setup_meta_boxes($post = null) {
    add_meta_box('orbisius_resume_organizer_resume_properties', 'Resume: Applicant Details', 'orbisius_resume_organizer_resume_properties_content',
            ORBISIUS_RESUME_ORGANIZER_POST_TYPE, 'normal', 'high');
}

/**
 * A central point e.g. when serving downloads.
 * for now we'll use it for download
 */
function orbisius_resume_organizer_handle_non_ui_calls() {
    if (!empty($_REQUEST['orb_res_org_cmd'])) {
        $errors = array();

        try {
            $cmd = $_REQUEST['orb_res_org_cmd'];
            $prod_id = empty($_REQUEST['prod_id']) ? '' : preg_replace('#[^\d-]#si', '', $_REQUEST['prod_id']);// 123
            $attachment_id = empty($_REQUEST['attachment_id']) ? '' : preg_replace('#[^\d-]#si', '', $_REQUEST['attachment_id']);// 123

            switch ($cmd) {
                case 'dl':
                    $product_obj = new Orbisius_Resume_Item();
                    
                    $product_data = $product_obj->get_product($prod_id, 1);

                    if (empty($product_data) || !current_user_can('manage_options')) {
                        throw new Exception("Invalid resume. #1000");
                    }

                    if (empty($product_data['attachments'][$attachment_id])) {
                        throw new Exception("Invalid resume. #1100");
                    } else {
                        $attachment_rec = $product_data['attachments'][$attachment_id];
                        Orbisius_Resume_Organizer_File::download_file($attachment_rec['file_src']);
                    }

                    break;

                default:
                   throw new Exception("Invalid command or something went terribly wrong.");
                   break;
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();

            $html = "<h3>Missing or invalid command data</h3>";
            $html .= "Errors: <br/>" . join("<br/>", $errors);
            $html .= "<br/> <br/> Go <a href='/'>&larr; Back</a> to home page. ";

            wp_die($html, 'Error');
        }

        exit;
    }
}

function orbisius_resume_organizer_get_fields() {
    $resume_fields = array(
        '_orb_res_org_email' => 'Email',
        '_orb_res_org_phone' => 'Phone',
        /*'_orb_res_org_twitter_url' => 'Twitter URL',
        '_orb_res_org_facebook_url' => 'Facebook URL',*/
        '_orb_res_org_site' => 'Site',
    );
    
    return $resume_fields;
}

/**
 *
 * @param type $post
 */
function orbisius_resume_organizer_resume_properties_content($post) {
    $prod_id = $post->ID;
    $meta = get_post_meta($prod_id);

    $fields = orbisius_resume_organizer_get_fields();

    $attachment_types = array(
        '' => '',
        Orbisius_Resume_Item::ATTACHMENT_TYPE_RESUME => 'Resume',
        Orbisius_Resume_Item::ATTACHMENT_TYPE_COVER_LETTER => 'Cover Letter',
        Orbisius_Resume_Item::ATTACHMENT_TYPE_REFERENCES => 'References',
        Orbisius_Resume_Item::ATTACHMENT_TYPE_PHOTO => 'Photo',
        Orbisius_Resume_Item::ATTACHMENT_TYPE_OTHER => 'Other',
    );

	$product_obj = new Orbisius_Resume_Item();
    $attachments_rec = $product_obj->get_attachments($prod_id);

    $buff = '';

    $buff .= "<h3>Attached Files</h3>\n";
    $buff .= "<table class='app_downloads_table' cellspacing='0' cellpadding='0'>\n";
    $buff .= "<tr class='app_table_header_row'>\n";
    $buff .= "<td class='plugin_number'>#</td>\n";
    $buff .= "<td class='plugin_name_cell'>File</td>\n";
    $buff .= "<td class='data_cell'>Type</td>\n";
    $buff .= "<td class='data_cell'>Size</td>\n";
    $buff .= "<td class='data_cell'>Ext</td>\n";
    $buff .= "<td class='download_url_cell'>Action</td>\n";
    $buff .= "</tr>\n";

    $cnt = 1;

    if (empty($attachments_rec)) {
        $buff .= "<tr>\n";
        $buff .= "<td class='plugin_number' colspan='5'>No attachments yet.</td>\n";
        $buff .= "</tr>\n";
    } else {
        foreach ($attachments_rec as $attachment_id => $attachment) {
             $dl_url = site_url('/?' . http_build_query(array(
                     'orb_res_org_cmd' => 'dl',
                     'prod_id' => $prod_id,
                     'attachment_id' => $attachment_id,
                 )
             ));
             
             /*$preview_url = 'http://docs.google.com/viewer?' . http_build_query(array(
                     'url' => $dl_url,
                 )
             );*/

             $nonce = wp_create_nonce("orbisius_resume_organizer_del_attachment_$attachment_id"); // unique nonce per attachment
             $link = admin_url('admin-ajax.php?action=orbisius_resume_organizer_ajax_del_attachment&attachment_id=' . $attachment_id . '&nonce=' . $nonce);

             $type_dropdown = Orbisius_Resume_Organizer_Util::html_select('orbisius_resume_organizer_existing_attachments[' . $attachment_id. ']',
                     $attachment['product_type'], $attachment_types);

             $cnt++;
             $cls = $cnt % 2 != 0 ? 'app_table_row_odd' : '';
             $buff .= "<tr class='$cls app_table_data_row'>\n";
             $buff .= "<td class='plugin_number'>$attachment_id</td>\n";
             $buff .= "<td class='plugin_name_cell'>{$attachment['title']}</td>\n";
             $buff .= "<td class='data_cell'>{$type_dropdown}</td>\n";
             $buff .= "<td class='data_cell'>{$attachment['file_size_label']}</td>\n";
             $buff .= "<td class='data_cell'>{$attachment['file_ext']}</td>\n";
             // <a href='$preview_url' target='_blank'>Preview</a>
             $buff .= "<td class='download_url_cell'>
                <a href='$dl_url'>Download</a>
                | <a class='orbisius_resume_organizer_admin_delete_attachment' href='$link' data-id='$attachment_id' data-nonce='$nonce'>Delete</a> </td>\n";
             $buff .= "</tr>\n";
        }

        $buff .= "<tr>\n";
        $buff .= "<td class='plugin_number' colspan='6'><p>Note: if you want to change the attachment type do
            it by selecting a different type from the dropdown manu and then click on Update (Right Sidebar).</p></td>\n";
        $buff .= "</tr>\n";
    }

    $buff .= "</table>\n";
    
    ?>
    <p>Please fill out the information below.</p>
    <input type="hidden" name="orbisius_resume_organizer_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>" />
    <table class="form-table">
        <?php foreach ($fields as $key => $label) : ?>
            <?php $value = empty($meta[$key]) ? '' : $meta[$key][0]; ?>
            <tr>
                <th><label for="<?php echo $key;?>"><?php echo esc_attr($label); ?>:</label></th>
                <td><input type="text" name="<?php echo $key;?>" value="<?php echo esc_attr($value); ?>" class="widefat" /></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <th><label for="tut_dur_hour">Attachments</label></th>
            <td>
                <div>To upload a file select it from your computer and click on Publish or Save Draft</div>

                <p>
                    File: <input type="file" name="_orb_res_org_file_1" value="" />
                    Type: <?php echo Orbisius_Resume_Organizer_Util::html_select('_orb_res_org_file_type_1', null, $attachment_types); ?>
                </p>
                <p>
                    File: <input type="file" name="_orb_res_org_file_2" value="" />
                    Type: <?php echo Orbisius_Resume_Organizer_Util::html_select('_orb_res_org_file_type_2', null, $attachment_types); ?>
                </p>
                <p>
                    File: <input type="file" name="orb_res_org_file_3" value="" />
                    Type: <?php echo Orbisius_Resume_Organizer_Util::html_select('_orb_res_org_file_type_3', null, $attachment_types); ?>
                </p>

                 <?php
                    echo $buff;
                ?>
            </td>
        </tr>
    </table>

    <?php
}

class Orbisius_Resume_Item {
    public function __construct() {

    }

    private $last_product = null;

    const LOAD_ATTACHMENTS = 1;

    const ATTACHMENT_TYPE_RESUME = 'Resume';
    const ATTACHMENT_TYPE_COVER_LETTER = 'Cover Letter';
    const ATTACHMENT_TYPE_REFERENCES = 'References';
    const ATTACHMENT_TYPE_PHOTO = 'Photo';
    const ATTACHMENT_TYPE_OTHER = 'Other';

    /**
     * Gets product data and formats it a little bit
     *
     * @param int $id
     * @param int $fetch_attachments
     */
    public function get_product($id, $fetch_attachments = 0) {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception("Invalid product. #2001");
        }

        $data = get_post($id, ARRAY_A);

        if (empty($data)) {
            throw new Exception("Invalid product. #2002");
        }

        if ($fetch_attachments) {
            $data['attachments'] = $this->get_attachments($id);
        }

        return $data;
    }
    /**
     *
     * @param int $post_id
     * @return array
     */
    public function get_attachments($post_id = 0) {
        if (empty($post_id) && !empty($this->last_product)) {
            $post_id = $this->last_product['id'];
        }

        $data = Orbisius_Resume_Organizer_Util::get_items('attachment', array(/*'user_id' => $current_user->ID, */ 'post_parent' => $post_id, ));

        foreach ($data as $idx => $rec) {
            $data[$idx]['file_src'] = get_attached_file($rec['id']);
            $data[$idx]['file_ext'] = end(explode('.', $data[$idx]['file_src']));
            $data[$idx]['file_size'] = filesize($data[$idx]['file_src']);
            $data[$idx]['file_size_label'] = Orbisius_Resume_Organizer_File::formatFileSize($data[$idx]['file_size']);
            $data[$idx]['file_src_url'] = wp_get_attachment_url($rec['id']);

            if (!empty($rec['meta_data']['_orb_mkt_attachment_type'])) {
                $data[$idx]['product_type'] = $rec['meta_data']['_orb_mkt_attachment_type'][0];

                switch ($data[$idx]['product_type']) {
                    case Orbisius_Resume_Item::ATTACHMENT_TYPE_RESUME:
                        $label = 'Resume';
                        break;

                    case Orbisius_Resume_Item::ATTACHMENT_TYPE_COVER_LETTER:
                        $label = 'Cover Letter';
                        break;

                    case Orbisius_Resume_Item::ATTACHMENT_TYPE_REFERENCES:
                        $label = 'References';
                        break;

                    case Orbisius_Resume_Item::ATTACHMENT_TYPE_OTHER:
                        $label = 'Other';
                        break;

                    default:
                        $label = 'N/A';
                        break;
                }

                $data[$idx]['product_type_label'] = $label;
            } else {
                $data[$idx]['product_type'] = 'N/A';
                $data[$idx]['product_type_label'] = 'N/A';
            }
        }

        return $data;
    }
}

/**
 * This function handles the post saving. We are going to extract data from some hidden fields
 * and will update post's meta.
 *
 * @param int $post_id
 * @return int
 */
function orbisius_resume_organizer_save_post($post_id) {
    if (wp_is_post_revision($post_id)
            || (isset($_POST['orbisius_resume_organizer_nonce']) && !wp_verify_nonce($_POST['orbisius_resume_organizer_nonce'], basename(__FILE__)))
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    $fields = orbisius_resume_organizer_get_fields();

    foreach ($fields as $key_val => $label) {
        if (isset($_POST[$key_val])) {
            $val = $_POST[$key_val];
            $val = Orbisius_Resume_Organizer_Util::strip_tags($val);
            
            update_post_meta($post_id, $key_val, $val);
        }
    }

    // the user wants to change attachment type fields of existing attachments
    if (!empty($_REQUEST['orbisius_resume_organizer_existing_attachments'])) {
        foreach ($_REQUEST['orbisius_resume_organizer_existing_attachments'] as $attachment_id => $attachment_type) {
            $val = wp_kses($attachment_type, array());
            update_post_meta($attachment_id, '_orb_mkt_attachment_type', $val);
        }
    }

    // http://wordpress.org/extend/ideas/topic/wp_handle_upload-should-create-the-resized-versions-of-images
    foreach($_FILES as $field => $file) {
        if (preg_match('#orb_res_org_file#si', $field) && $file['size'] >= 1) {
            add_filter('wp_handle_upload_prefilter', 'orbisius_resume_organizer_modify_upload_file_prefilter');
            add_filter('upload_dir', 'orbisius_resume_organizer_modify_upload_dir');

            $override['action'] = 'editpost';

            $uploaded_file = wp_handle_upload($file, $override);

            remove_filter('wp_handle_upload_prefilter', 'orbisius_resume_organizer_modify_upload_file_prefilter');
            remove_filter('upload_dir', 'orbisius_resume_organizer_modify_upload_dir');

            $attachment = array(
                'post_title' => Orbisius_Resume_Organizer_File::toHumanRadable($file['name']),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_type' => 'attachment',
                'post_parent' => $post_id,
                'post_mime_type' => $file['type'],
                'guid' => $uploaded_file['url'],
            );

            // Create an Attachment in WordPress
            $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file'], $post_id);
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']));
            update_post_meta($post_id, $field, $attachment_id);

            // let's check the file type (from the dropdown).
            // orb_res_org_file_1 -> orb_res_org_file_type_1
            $file_type_field_name = $field;
            $file_type_field_name = str_replace('_file_', '_file_type_', $field);

            if (!empty($_REQUEST[$file_type_field_name])) {
                $val = wp_kses($_REQUEST[$file_type_field_name], array());
                update_post_meta($attachment_id, '_orb_mkt_attachment_type', $val);
            }

            //Remove it from the array to avoid duplicating during autosave/revisions.
            unset($_FILES[$field]);
        } // orb meta upload
    }
}

/**
 * Puts the uploads in their own folder + makes the files go in a deep folder stucture. /a/b/c
 *
 * Contains these keys.
 * array (
    'path' => 'C:/projects/default/htdocs/wordpress/wp-content/uploads/2012/12',
    'url' => 'http://localhost/wordpress/wp-content/uploads/2012/12',
    'subdir' => '/2012/12',
    'basedir' => 'C:/projects/default/htdocs/wordpress/wp-content/uploads',
    'baseurl' => 'http://localhost/wordpress/wp-content/uploads',
    'error' => false,
    )
 *
 * Result after injecting tutorial's folder
 * array(6) {
  ["path"]=>  string(77) "C:/projects/default/htdocs/wordpress/wp-content/uploads/products/media/6/4/5"
  ["url"]=>   string(67) "http://localhost/wordpress/wp-content/uploads/products/media/6/4/5"
  ["subdir"]=>  string(22) "/products/media/6/4/5/1"
  ["basedir"]=>   string(55) "C:/projects/default/htdocs/wordpress/wp-content/uploads"
  ["baseurl"]=>   string(45) "http://localhost/wordpress/wp-content/uploads"
  ["error"]=>   bool(false)
}
 * @param array $path
 * @return string
 */
function orbisius_resume_organizer_modify_upload_dir( $path_fields ) {
     global $post_id;

     if (empty($path_fields['error'])) { // upload is OK
         if (empty($post_id) && !empty($_REQUEST['post_id'])) {
             $post_id = $_REQUEST['post_id'];
         }

         $upload_dir_basename = basename($path_fields['basedir']); // get the dirname .. it'll be 'uploads' in most cases.
         $hash = Orbisius_Resume_Organizer_Util::hash($post_id);
         $deep_dir = substr($hash, 0, 1) . '/' . substr($hash, 1, 1) . '/' . substr($hash, 2, 1) . '/' . substr($hash, 3, 1);
         $root_resume_dir = '/orbisius-resume-organizer/files/'; // if you change /files/ update the the following not to use dirname

         // if we don't have an htaccess file put it there; even above files
         $ht_access_root = $path_fields['basedir'] . dirname($root_resume_dir) . '/.htaccess';

         if (!file_exists($ht_access_root)) {
            $ht_buff = 'deny from all';
            file_put_contents($ht_access_root, $ht_buff);
         }

         $index_root = $path_fields['basedir'] . dirname($root_resume_dir) . '/index.html';

         if (!file_exists($index_root)) {
            @touch($index_root);
         }
         
         $subdir = $root_resume_dir . $deep_dir;

         // append our sub dir(see above) to the upload folder.
         $path = preg_replace('#(.*?' . preg_quote($upload_dir_basename) . ').*#si', '\\1' . $subdir, $path_fields['path']);
         $url = preg_replace('#(.*?' . preg_quote($upload_dir_basename) . ').*#si', '\\1' . $subdir, $path_fields['url']);

         $path_fields['subdir'] = $subdir;
         $path_fields['path'] = $path;
         $path_fields['url'] = $url;

         if (!is_dir($path)) {
            mkdir($path, 0777, 1);
         }
     }

     return $path_fields;
}

/**
 * Formats the filename name: makes it lowercase, removes spaces by replacing them with '-'.
 * The functions hooks before the upload is handled.
 * @param array $file_rec
 * @return string
 */
function orbisius_resume_organizer_modify_upload_file_prefilter($file_rec, $sep = '-') {
    $file = $file_rec['name'];
    $file = basename($file);

    $ext = '';

    if (empty($ext) && (strpos($file, '.') !== false)) {
        $ext = @end(explode('.', $file));
    }

    $file = urldecode($file); // sometimes there are %20 and other chars
    $file = str_replace($ext, '', $file);

    $ext = preg_replace('#[^a-z\d]#si', '', $ext);

    if (function_exists('iconv')) {
        $src    = "UTF-8";
        // If you append the string //TRANSLIT to out_charset  transliteration is activated.
        $target = "ISO-8859-1//TRANSLIT";
        $file =  iconv($src, $target, $file);
    }

    $file = preg_replace('#[^\w\-]+#',  $sep, $file);
    $file = preg_replace('#[\s\-\_]+#', $sep, $file);
    $file = trim($file, ' /\\ -_');

    // If there are non-english characters they will be replaced with entities which we'll use
    // as guideline to find the equivalent in English.
    $file = htmlentities($file);

    // non-enlgish -> english equivalent
    $file = preg_replace('/&([a-z][ez]?)(?:acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);/si', '\\1', $file);

    // remove any unrecognized entities
    $file = preg_replace('/&([a-z]+);/', '', $file);

    // remove any unrecognized entities
    $file = preg_replace('@\&\#\d+;@s', '', $file);
    $file = strtolower($file);

    // There are creazy people that may enter longer link :)
    $file = substr($file, 0, 200);

    $ext = preg_replace('#jpe?g#si', 'jpg', $ext);

    if (!empty($ext)) {
        $file .= '.' . $ext;
    }

    $file_rec['name'] = $file;

    return $file_rec;
}

function orbisius_resume_organizer_register_custom_content_types() {
    register_post_type(ORBISIUS_RESUME_ORGANIZER_POST_TYPE, array(
		'label' => 'Resumes',
        'menu_position' => 100, // show below Posts but above Media
		'description' => '',
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => false,
        //'capability_type' => 'post',
		'hierarchical' => false,
		'has_archive' => false,
		'rewrite' => false,
		'query_var' => true,
		'exclude_from_search' => true,
        'supports' => array('title', 'editor', 'custom-fields', /*'thumbnail', 'page-attributes', */ ),
        'labels' => array(
            'name' => 'Resumes',
            'singular_name' => 'Resume',
            'menu_name' => 'Manage',
            'add_new' => 'Add Resume',
            'add_new_item' => 'Add New Resume',
            'edit' => 'Edit',
            'edit_item' => 'Edit Resume',
            'new_item' => 'New Resume',
            'view' => 'View Resume',
            'view_item' => 'View Resume',
            'search_items' => 'Search Resumes',
            'not_found' => 'No Resumes Found',
            'not_found_in_trash' => 'No Resumes Found in Trash',
            'parent' => 'Parent Resume',
        ),
    ));

    //flush_rewrite_rules();
}

/**
 * Triggered when the plugin is activated.
 */
function orbisius_resume_organizer_on_activate() {
    orbisius_resume_organizer_register_custom_content_types();
    flush_rewrite_rules();
}

function orbisius_resume_organizer_init() {
    orbisius_resume_organizer_register_custom_content_types();
}

/**
 * @package Orbisius Resume Organizer
 * @since 1.0
 *
 * Searches through posts to see if any matches the REQUEST_URI.
 * Also searches tags
 */
function orbisius_resume_organizer_admin_init() {
    wp_register_style(dirname(__FILE__), plugins_url('/assets/main.css', __FILE__), false);
    wp_enqueue_style(dirname(__FILE__));
	
    if (is_admin()) {
         add_action('post_edit_form_tag', 'orbisius_resume_organizer_add_post_enctype');

        wp_register_script('orbisius_resume_organizer_admin_main_script', plugins_url('/assets/main_admin.js', __FILE__), array('jquery', ), '1.0', true);
        wp_enqueue_script('orbisius_resume_organizer_admin_main_script');
        
        orbisius_resume_organizer_setup_meta_boxes();
    }

    //add_action('admin_menu', 'orbisius_resume_organizer_create_menu');
    //add_action('wp_head', 'orbisius_resume_organizer_setup_config', 1);
    //add_action('wp_footer', 'orbisius_resume_organizer_setup_link', 9999);
}

/**
 * Adds the enctype of the new/post type so it handles uploads
 */
function orbisius_resume_organizer_add_post_enctype() {
    echo ' enctype="multipart/form-data" ';
}

/**
 * Set up administration
 *
 * @package Orbisius Resume Organizer
 * @since 0.1
 */
function orbisius_resume_organizer_setup_admin() {
    //add_options_page( 'Orbisius Resume Organizer', 'Orbisius Resume Organizer', 'manage_options', __FILE__, 'orbisius_resume_organizer_menu_settings' );

	// when plugins are show add a settings link near my plugin for a quick access to the settings page.
	add_filter('plugin_action_links', 'orbisius_resume_organizer_add_plugin_settings_link', 10, 2);

    add_menu_page('Orbisius Resume Organizer', 'Orbisius Resume Organizer', 'manage_options', dirname(__FILE__) . '-dashboard',
            'orbisius_resume_organizer_menu_dashboard', plugins_url('/assets/icons/attach.png', __FILE__), 100);

    $imp_tool = dirname(__FILE__) . '-dashboard';
    add_submenu_page($imp_tool, 'Manage', 'Manage', 'manage_options', 'edit.php?post_type=' . urlencode(ORBISIUS_RESUME_ORGANIZER_POST_TYPE));
    add_submenu_page($imp_tool, 'Add Resume', 'Add Resume', 'manage_options', 'post-new.php?post_type=' . urlencode(ORBISIUS_RESUME_ORGANIZER_POST_TYPE));
    add_submenu_page($imp_tool, 'Settings', 'Settings', 'manage_options', dirname(__FILE__) . '-settings', 'orbisius_resume_organizer_menu_settings');
    add_submenu_page($imp_tool, 'Help', 'Help', 'manage_options', dirname(__FILE__) . '-help', 'orbisius_resume_organizer_menu_help');
    add_submenu_page($imp_tool, 'Extensions', 'Extensions', 'manage_options', dirname(__FILE__) . '-extensions', 'orbisius_resume_organizer_menu_extensions');
    add_submenu_page($imp_tool, 'About', 'About', 'manage_options', dirname(__FILE__) . '-about', 'orbisius_resume_organizer_menu_about');
}

// Add the ? settings link in Plugins page very good
function orbisius_resume_organizer_add_plugin_settings_link($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
        $prefix = 'edit.php?post_type=' . urlencode(ORBISIUS_RESUME_ORGANIZER_POST_TYPE);
        $dashboard_link = "<a href=\"{$prefix}\">" . 'Manage' . '</a>';

        array_unshift($links, $dashboard_link);
        
        $prefix = 'post-new.php?post_type=' . urlencode(ORBISIUS_RESUME_ORGANIZER_POST_TYPE);
        $dashboard_link = "<a href=\"{$prefix}\">" . 'Add' . '</a>';

        array_unshift($links, $dashboard_link);
    }

    return $links;
}

function orbisius_resume_organizer_support_html() {
    ?>
    <h2>Orbisius Resume Organizer - Support &amp; Premium Plugins</h2>
		<div class="app-alert-notice">
			<p>
			** NOTE: ** We have launched our  
            
            <a href="http://club.orbisius.com/?utm_source=wp_orbisius_resume_organizer&utm_medium=admin-support&utm_campaign=orbisius_resume_organizer" target="_blank">Club Orbisius</a>
        site which offers lots of free and premium plugins, video tutorials and more. The support is handled there as well.
			<br/>Please do NOT use the WordPress forums or other places to seek support.
			</p>
		</div>

        <h2>Want to hear about future plugins? Join our mailing List! (no spam)</h2>
            <p>
                Get the latest news and updates about this and future cool <a href="http://profiles.wordpress.org/lordspace/"
                                                                                target="_blank" title="Opens a page with the pugins we developed. [New Window/Tab]">plugins we develop</a>.
            </p>

            <p>
                <!-- // MAILCHIMP SUBSCRIBE CODE \\ -->
                1) Subscribe by going to <a href="http://eepurl.com/guNzr" target="_blank">http://eepurl.com/guNzr</a>
                <!-- \\ MAILCHIMP SUBSCRIBE CODE // -->
             OR
                2) by using our QR code. [Scan it with your mobile device].<br/>
                <img src="<?php echo plugin_dir_url(__FILE__); ?>/i/guNzr.qr.2.png" alt="" />
            </p>

            <?php if (1) : ?>
            <?php
                $plugin_data = get_plugin_data(__FILE__);

                $app_link = urlencode($plugin_data['PluginURI']);
                $app_title = urlencode($plugin_data['Name']);
                $app_descr = urlencode($plugin_data['Description']);
            ?>

            <?php if (0) : ?>
            <h2>Demo</h2>
            <p>
				<iframe width="560" height="315" src="http://www.youtube.com/embed/BZUVq6ZTv-o" frameborder="0" allowfullscreen></iframe>

				<br/>Video Link: <a href="http://www.youtube.com/watch?v=BZUVq6ZTv-o&feature=youtu.be" target="_blank">http://www.youtube.com/watch?v=BZUVq6ZTv-o&feature=youtu.be</a>
			</p>
            <?php endif; ?>

			<h2>Share with friends</h2>
            <p>
                <!-- AddThis Button BEGIN -->
                <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
                    <a class="addthis_button_facebook" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_twitter" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_google_plusone" g:plusone:count="false" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_linkedin" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_email" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_myspace" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_google" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_digg" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_delicious" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_stumbleupon" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_tumblr" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_favorites" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                    <a class="addthis_button_compact"></a>
                </div>
                <!-- The JS code is in the footer -->

                <script type="text/javascript">
                var addthis_config = {"data_track_clickback":true};
                var addthis_share = {
                  templates: { twitter: 'Check out {{title}} #wordpress #plugin at {{lurl}} (via @orbisius)' }
                }
                </script>
                <!-- AddThis Button START part2 -->
                <script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js"></script>
                <!-- AddThis Button END part2 -->
            </p>
            <?php endif ?>
    <?php
}

/**
 * Upload page.
 * Ask the user to upload a file
 * Preview
 * Process
 *
 * @package Permalinks to Category/Permalinks
 * @since 1.0
 */
function orbisius_resume_organizer_menu_dashboard() {
    $plugin_data = get_plugin_data(__FILE__);

    $app_title = $plugin_data['Name'];
    $app_descr = $plugin_data['Description'];

    $app_descr = str_replace('<a ', '<a target="_blank" ', $app_descr);

    ?>
    <div class="wrap orbisius-resume_organizer-container">
        <h2>Orbisius Resume Organizer</h2>

        <div class="updated">
            <p><?php echo $app_descr;?></p>
        </div>

		<?php orbisius_resume_organizer_support_html(); ?>
    </div>
    <?php
}

/**
 * Upload page.
 * Ask the user to upload a file
 * Preview
 * Process
 *
 * @package Permalinks to Category/Permalinks
 * @since 1.0
 */
function orbisius_resume_organizer_menu_settings() {
    ?>
    <div class="wrap orbisius-resume_organizer-container">
        <h2>Orbisius Resume Organizer</h2>

        <form id="orbisius_resume_organizer_form" class="orbisius_resume_organizer_form" method="post">
            <?php wp_nonce_field( basename(__FILE__) . '-action', 'orbisius_resume_organizer_nonce' ); ?>
            <div class="updated">
                <p>The plugin doesn't have any options at the moment.</p>
            </div>
        </form>

		<?php orbisius_resume_organizer_support_html(); ?>
    </div>
    <?php
}

function orbisius_resume_organizer_menu_help() {
    ?>
    <div class="wrap">
        <?php orbisius_resume_organizer_support_html(); ?>
    </div>
    <?php
}

function orbisius_resume_organizer_menu_extensions() {
    ?>
    <div class="wrap orbisius-resume_organizer-container">
        <h2>Orbisius Resume Organizer - Extensions</h2>

        <form id="orbisius_resume_organizer_form" class="orbisius_resume_organizer_form" method="post">
            <?php wp_nonce_field( basename(__FILE__) . '-action', 'orbisius_resume_organizer_nonce' ); ?>
            <div class="updated">
                <p>The plugin doesn't have any extensions at the moment.</p>
            </div>
        </form>
    </div>
    <?php
}

function orbisius_resume_organizer_menu_about() {
    ?>
    <div class="wrap orbisius-resume_organizer-container">
        <h2>Orbisius Resume Organizer - About</h2>

        <p>
            The plugin was created by Slavi Marinov. I love creating plugins. <br/>
            <p>If you like this plugin you may want to check my other
            <a href="http://club.orbisius.com/products/wordpress-plugins/?utm_source=wp_orbisius_resume_organizer&utm_medium=admin-about&utm_campaign=orbisius_resume_organizer"
               target="_blank">plugins</a>.
            </p>
            
            <p>If you want to support my work become a premium subscriber to my
            <a href="http://club.orbisius.com/?utm_source=wp_orbisius_resume_organizer&utm_medium=admin-about&utm_campaign=orbisius_resume_organizer" target="_blank">Club Orbisius</a>
             site which gives you access to premium plugins and extensions.
            You will also help for the development of future plugins.
            </p>
        </p>
    </div>
    <?php
}

/**
* adds some HTML comments in the page so people would know that this plugin powers their site.
*/
function orbisius_resume_organizer_add_plugin_credits() {
    // pull only these vars
    $default_headers = array(
		'Name' => 'Plugin Name',
		'PluginURI' => 'Plugin URI',
	);

    $plugin_data = get_file_data(__FILE__, $default_headers, 'plugin');

    $url = $plugin_data['PluginURI'];
    $name = $plugin_data['Name'];
    
    printf(PHP_EOL . PHP_EOL . '<!-- ' . "Powered by $name | URL: $url " . '-->' . PHP_EOL . PHP_EOL);
}

/**
 */
class orbisius_resume_organizer {
    public $result = null;
    public $target_dir_path; // /var/www/vhosts/domain.com/www/wp-content/themes/Parent-Theme-child-01/

    /**
     * Sets up the params.
     * directories contain trailing slashes.
     * 
     * @param str $parent_theme_basedir
     */
    public function __construct($parent_theme_basedir = '') {
        $all_themes_root = get_theme_root();
        
        $this->parent_theme_basedir = $parent_theme_basedir;
        $this->parent_theme_dir = $all_themes_root . '/' . $parent_theme_basedir . '/';

        $i = 0;

        // Let's create multiple folders in case the script is run multiple times.
        do {
            $i++;
            $target_dir = $all_themes_root . '/' . $parent_theme_basedir . '-child-' . sprintf("%02d", $i) . '/';
        } while (is_dir($target_dir));

        $this->target_dir_path = $target_dir;
        $this->target_base_dirname = dirname($target_dir);

        // this is appended to the new theme's name
        $this->target_name_suffix = 'Child ' . sprintf("%02d", $i);
    }

    /**
     * Loads files from a directory but skips . and ..
     */
    public function load_files($dir) {
        $files = array();
        $all_files = scandir($dir);

        foreach ($all_files as $file) {
            if ($file == '.' || $file == '..') {
				continue;
			}

            $files[] = $file;
        }

        return $files;
    }

    private $info_result = 'n/a';
    private $data_file = '.ht_orbisius_resume_organizer.json';

    /**
     * Checks for correct permissions by trying to create a file in the target dir
     * Also it checks if there are files in the target directory in case it exists.
     */
    public function check_permissions() {
        $target_dir_path = $this->target_dir_path;
        
        if (!is_dir($target_dir_path)) {
            if (!mkdir($target_dir_path, 0775)) {
                throw new Exception("Target child theme directory cannot be created. This is probably a permission error. Cannot continue.");
            }
        } else { // let's see if there will be files in that folder.
            $files = $this->load_files($target_dir_path);

            if (count($files) > 0) {
                throw new Exception("Target folder already exists and has file(s) in it. Cannot continue. Files: ["
                        . join(',', array_slice($files, 0, 5)) . ' ... ]' );
            }
        }

        // test if we can create the folder and then delete it.
        if (!touch($target_dir_path . $this->data_file)) {
            throw new Exception("Target directory is not writable.");
        }
    }
    
    /**
     * Copy some files from the parent theme.
     * @return bool success
     */
    public function copy_main_files() {
        $stats = 0;
        $main_files = array('screenshot.png', 'footer.php', 'license.txt');

        foreach ($main_files as $file) {
            if (!file_exists($this->parent_theme_dir . $file)) {
                continue;
            }

            $stat = copy($this->parent_theme_dir . $file, $this->target_dir_path . $file);
            $stat = intval($stat);
            $stats += $stat;
        }
    }

    /**
     *
     * @return bool success
     * @see http://codex.wordpress.org/Child_Themes
     */
    public function generate_style() {
        global $wp_version;
        
        $plugin_data = get_plugin_data(__FILE__);
        $app_link = $plugin_data['PluginURI'];
        $app_title = $plugin_data['Name'];

        $parent_theme_data = version_compare($wp_version, '3.4', '>=')
                ? wp_get_theme($this->parent_theme_basedir)
                : (object) get_theme_data($this->target_dir_path . 'style.css');

        $buff = '';
        $buff .= "/*\n";
        $buff .= "Theme Name: $parent_theme_data->Name $this->target_name_suffix\n";
        $buff .= "Theme URI: $parent_theme_data->ThemeURI\n";
        $buff .= "Description: $this->target_name_suffix theme for the $parent_theme_data->Name theme\n";
        $buff .= "Author: $parent_theme_data->Author\n";
        $buff .= "Author URI: $parent_theme_data->AuthorURI\n";
        $buff .= "Template: $this->parent_theme_basedir\n";
        $buff .= "Version: $parent_theme_data->Version\n";
        $buff .= "*/\n";

        $buff .= "\n/* Generated by $app_title ($app_link) on " . date('r') . " */ \n\n";

        $buff .= "@import url('../$this->parent_theme_basedir/style.css');\n";
        
        file_put_contents($this->target_dir_path . 'style.css', $buff);

        // RTL langs; make rtl.css to point to the parent file as well
        if (file_exists($this->parent_theme_dir . 'rtl.css')) {
            $rtl_buff .= "/*\n";
            $rtl_buff .= "Theme Name: $parent_theme_data->Name $this->target_name_suffix\n";
            $rtl_buff .= "Template: $this->parent_theme_basedir\n";
            $rtl_buff .= "*/\n";

            $rtl_buff .= "\n/* Generated by $app_title ($app_link) on " . date('r') . " */ \n\n";

            $rtl_buff .= "@import url('../$this->parent_theme_basedir/rtl.css');\n";

            file_put_contents($this->target_dir_path . 'rtl.css', $rtl_buff);
        }

        $this->info_result = "$parent_theme_data->Name " . $this->target_name_suffix . ' has been created in ' . $this->target_dir_path
                . ' based on ' . $parent_theme_data->Name . ' theme.'
                . "\n<br/>Next Go to Appearance > Themes and Activate the new theme.";
    }

    /**
     *
     * @return string
     */
    public function get_details() {
        return $this->info_result;
    }

    /**
     *
     * @param type $filename
     */
    function log($msg) {
        error_log($msg . "\n", 3, ini_get('error_log'));
    }
}

class Orbisius_Resume_Organizer_Util {
    /**
     * Calculates unique hash based on the string.
     *
     * @param type $str
     * @return type
     */
    public static function hash($str, $just_sha = 0) {
        if ($just_sha) {
            $str = sha1($str);
        } else {
            $str = sha1(sha1(md5($str) . '1981-sfasfasfjahasfasfASDFASFASFASFASF') . 'asfasfaASFASF.sf9089780.afakljsfljkahsfukhasf');
        }

        return $str;
    }
    
    /**
     * Uses WP's wp_kses to clear the text
     */
    public static function strip_tags($buffer) {
        $buffer = wp_kses($buffer, array(
                'div' => array(),
                'p' => array(),
                'a' => array(
                    'href' => array(),
                    'title' => array(),
                    'target' => array(),
                ),
                'ul' => array(
                    'class' => array(),
                ),
                'ol' => array(
                    'class' => array(),
                ),
                'li' => array(
                    'class' => array(),
                ),
                'br' => array(),
                'hr' => array(),
                'strong' => array(),
            )
        );

        $buffer = trim($buffer);

        return $buffer;
    }

    /**
     * Retrieves all the posts matching given post type.
     * If the type is attachment we set the publish status to inherit because
     * the post status is set to published if not specified.
     */
    public static function get_items($post_type = '', $filters = array()) {
        $limit = -1;
        $items = array();

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => $limit,
        );

        if (!empty($filters['post_parent'])) {
            $args['post_parent'] = $filters['post_parent'];
        }

        if (!empty($filters['post_status'])) {
            $args['post_status'] = $filters['post_status'];
        } elseif ($post_type == 'attachment') {
            $args['post_status'] = 'inherit';
        }

        if (!empty($filters['user_id'])) {
            $args['post_author'] = $filters['user_id'];
        }

        if (!empty($filters['limit'])) {
            $args['posts_per_page'] = $filters['limit'];
        }

        if (!empty($filters['meta'])) {
            $args['meta_query'] = array(
                array(
                    'key' => $filters['meta']['key'],
                    'value' => $filters['meta']['value'],
                    'compare' => '='
                )
            );
        }

        // 
        /*if (!is_null($dept_slug)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'tag',
                    'terms' => $dept_slug,
                    'field' => 'slug',
                ),
            );
        }
        );*/

        $connected = new WP_Query($args);

        if ($connected->have_posts()) {
            while ($connected->have_posts()) {
                $connected->the_post();

                $rec['id'] = get_the_ID();
                $rec['title'] = get_the_title();
                $rec['date'] = get_the_date('F j, Y, g:i a');
                $rec['meta_data'] =  get_post_meta( get_the_ID() );

                $items[$rec['id']] = $rec;
            }
        }

        wp_reset_postdata();

        return $items;
    }

    /**
     * Outputs a message (adds some paragraphs).
     */
    public static function msg($msg, $status = 0) {
        $msg = join("<br/>\n", (array) $msg);

        if (empty($status)) {
            $cls = 'app-alert-error';
        } elseif ($status == 1) {
            $cls = 'app-alert-success';
        } else {
            $cls = 'app-alert-notice';
        }

        $str = "<div class='$cls'><p>$msg</p></div>";

        return $str;
    }

    /**
     * a simple status message, no formatting except color, simpler than its brothers
     */
    function m($msg, $status = 0, $use_inline_css = 0) {
        $cls = empty($status) ? 'app_error' : 'app_success';
        $inline_css = '';

        if ($use_inline_css) {
            $inline_css = empty($status) ? 'color:red;' : 'color:green;';
            $inline_css .= 'text-align:center;margin-left: auto; margin-right: auto;';
        }

        $str = <<<MSG_EOF
<span class='$cls' style="$inline_css">$msg</span>
MSG_EOF;
        return $str;
    }

    /**
     *
     * Appends a parameter to an url; uses '?' or '&'
     * It's the reverse of parse_str().
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    public static function get_req_uri($full = 1, $keep_params = 0) {
        $req_uri = $_SERVER['REQUEST_URI'];

        if (empty($keep_params)) {
            $req_uri = preg_replace('#\?.*#si', '', $req_uri);
            $req_uri = preg_replace('#\#.*#si', '', $req_uri);
        }

        $url = function_exists('site_url')
                ? site_url($req_uri)
                : 'http://' . $_SERVER['HTTP_HOST'] . $req_uri;

        return $url;
    }

    // generates HTML select
    public static function html_select($name = '', $sel = null, $options = array(), $attr = '') {
        $html = "\n" . '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" ' . $attr . '>' . "\n";

        foreach ($options as $key => $label) {
            $selected = $sel == $key ? ' selected="selected"' : '';
            $html .= "\t<option value='$key' $selected>$label</option>\n";
        }

        $html .= '</select>';
        $html .= "\n";

        return $html;
    }

    // generates HTML select
    public static function html_checkbox($name, $val = null, $msg = '', $attr = '') {
        //                                        <?php echo empty($opts['form_new_window']) ? '' : 'checked="checked"';
        $sel = '';
        $name = esc_attr($name);
        $val = esc_attr($val);
        $msg = esc_attr($msg);
        $html = "\n<label for='$name'><input type='checkbox' id='$name' name='$name' value='$val' $sel $attr /> $msg</label>\n";

        return $html;
    }

    /**
     *
     * Appends a parameter to an url; uses '?' or '&'
     * It's the reverse of parse_str().
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    public static function add_url_params($url, $params = array()) {
        $str = $url;

        if (empty($params)) {
            return $str;
        }

        $params = (array) $params;

        $str .= (strpos($url, '?') === false) ? '?' : '&';
        $str .= http_build_query($params);

        return $str;
    }
}

class Orbisius_Resume_Organizer_File {
    static function getAttachments($post_id) {
        // arguments for get_posts
        $attachment_args = array(
            'post_type' => 'attachment',
            //'post_mime_type' => 'image',
            //'post_status' => null, // attachments don't have statuses
            'post_parent' => $post_id,
        );

        // get the posts
        $my_attachment_objects = get_posts($attachment_args);

        return $my_attachment_objects;
    }

    static function toHumanRadable($file) {
        $file = basename($file);
        $file = preg_replace('#\.\w+$#si', '', $file); // rm ext
        $file = preg_replace('#[^\s\w]+#si', ' ', $file);
        $file = preg_replace('#\s+$#si', ' ', $file);
        $file = trim($file);

        return $file;
    }

    /**
     * Returns formatted file size. If integer is supplied it will be formated.
     * If a filename is supplied the function will assume that a file name is supplied.
     * @param mixed $file
     * @return string
     */
    static function formatFileSize($file) {
        $bytes = is_numeric($file) ? $file : filesize($file);

        $s = array('Bytes', 'KB', 'MB', 'GB');
        $e = floor(log($bytes) / log(1024));

        if (empty($bytes)) {
            return '0';
        }

        $label = sprintf('%.2f ' . $s[$e], ($bytes / pow(1024, floor($e))));
        $label = str_replace('.00', '', $label);

        return $label;
    }

    /**
     * Serves the file for download. Forces the browser to show Save as and not open the file in the browser.
     * Makes the script run for 12h just in case and after the file is sent the script stops.
     *
     * Credits:
	 * http://php.net/manual/en/function.readfile.php
     * http://stackoverflow.com/questions/2222955/idiot-proof-cross-browser-force-download-in-php
     *
     * @param string $file
     * @param bool $do_exit - exit after the file has been downloaded.
     */
    public static function download_file($file, $do_exit = 1) {
        set_time_limit(12 * 3600); // 12 hours

        if (ini_get('zlib.output_compression')) {
            @ini_set('zlib.output_compression', 0);

            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
        }

        if (!empty($_SERVER['HTTPS'])
                && ($_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)) {
            header("Cache-control: private");
            header('Pragma: private');

            // IE 6.0 fix for SSL
            // SRC http://ca3.php.net/header
            // Brandon K [ brandonkirsch uses gmail ] 25-Apr-2007 03:34
            header('Cache-Control: maxage=3600'); //Adjust maxage appropriately
        } else {
            header('Pragma: public');
        }

        // the actual file that will be downloaded
        $download_file_name = basename($file);

        // if a file with the same name existed we've appended some numbers to the filename but before
        // the extension. Now we'll offer the file without the appended numbers.
        $download_file_name = preg_replace('#-sss\d+(\.\w{2,5})$#si', '\\1', $download_file_name);

        $default_content_type = 'application/octet-stream';

        $ext = end(explode('.', $download_file_name));
        $ext = strtolower($ext);

        // http://en.wikipedia.org/wiki/Internet_media_type
        $content_types_array = array(
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            '7z' => 'application/x-7z-compressed',
            'gz' => 'application/x-gzip',
            'rar' => 'application/x-rar-compressed',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // ms office
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $content_type = empty($content_types_array[$ext]) ? $default_content_type : $content_types_array[$ext];

		header('Expires: 0');
 		header('Content-Description: File Transfer');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: ' . $content_type);
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string) (filesize($file)));
        header('Content-Disposition: attachment; filename="' . $download_file_name . '"');

		ob_clean();
		flush();

        readfile($file);

		if ($do_exit) {
			exit;
		}
    }
}

function orbisius_resume_organizer_ajax_del_attachment() {
   $attachment_id = empty($_REQUEST['attachment_id']) ? 0 : intval($_REQUEST['attachment_id']);

   $status_rec = array(
       'status' => 0,
   );

   $current_user_obj = wp_get_current_user();

   // do some checks before deleting this attachment.
   // ::SNOTE:: do we need a custom capability seller?
   if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
           && wp_verify_nonce($_REQUEST['nonce'], "orbisius_resume_organizer_del_attachment_$attachment_id")
           && is_user_logged_in()
           && !empty($current_user_obj)
           && user_can($current_user_obj->ID, 'delete_post', $attachment_id)
           ) {
      $force_delete = true;
      $status = wp_delete_attachment( $attachment_id, $force_delete );

      $status_rec['status'] = $status !== false;
   }

   $result = json_encode($status_rec);
   echo $result;

   exit();
}

function orbisius_resume_organizer_ajax_not_auth() {
   $status_rec = array(
       'status' => 0,
       'message' => "You must be logged in in order to perform this operation.",
   );

   $result = json_encode($status_rec);
   echo $result;

   exit();
}
