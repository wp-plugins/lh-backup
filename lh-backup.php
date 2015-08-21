<?php 
/*
Plugin Name: LH Backup
Plugin URI: http://lhero.org/plugins/lh-backup/
Description: Creates an email backup of tables in the database
Version: 1.1
Author: Peter Shaw
Author URI: http://shawfactor.com/
*/

class LH_backup_plugin {

var $filename;
var $options;
var $opt_name = 'lh_backup-options';
var $queue_name = 'lh_backup-queue';
var $page_id_field = 'lh_backup-attachment_page_id';
var $email_field = 'lh_backup-email_addresses';
var $queries_field = 'lh_backup-queries_field';

private function arrayToCsv( $fields, $delimiter = ';', $enclosure = '"', $encloseAll = false, $nullToMysqlNull = false ) {
    $delimiter_esc = preg_quote($delimiter, '/');
    $enclosure_esc = preg_quote($enclosure, '/');

    $output = array();
    foreach ( $fields as $field ) {
        if ($field === null && $nullToMysqlNull) {
            $output[] = 'NULL';
            continue;
        }

        // Enclose fields containing $delimiter, $enclosure or whitespace
        if ( $encloseAll || preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field ) ) {
            $output[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
        }
        else {
            $output[] = $field;
        }
    }

    return implode( $delimiter, $output );
}



private function sanitize_query__string($string){

// Removes dangerous functions from sql
$danger = array("delete", "drop", "update","alter","insert");
$return = str_ireplace($danger, "", $string);

return $return;

}

private function generate_csv_string($vararray){
	
$csvString = '';

$bar = array_flip((array)$vararray[0]);

$csvString .= $this->arrayToCsv($bar,",")."\n";

foreach ($vararray as $fields) {

$csvString .= $this->arrayToCsv($fields,",")."\n";

 }

return $csvString;


}


private function array_fix( $array )    {
        return array_filter(array_map( 'trim', $array ));

}

private function create_file($name,$vararray){



$string = $this->generate_csv_string($vararray);

$upload_dir = wp_upload_dir();

$file = $upload_dir['path']."/".$name.".csv";

file_put_contents($file, $string);


$url = $upload_dir['url']."/".$name.".csv";

$tmp = download_url( $url );
$post_id = $this->options[$this->page_id_field];

	$file_array = array();

	// Set variables for storage
	// fix file filename for query strings

preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png|csv)/i', $url, $matches);
	$file_array['name'] = basename($matches[0]);
	$file_array['tmp_name'] = $tmp;

	// If error storing temporarily, unlink
	if ( is_wp_error( $tmp ) ) {

print_r($tmp);
		@unlink($file_array['tmp_name']);
		$file_array['tmp_name'] = '';
	}

	// do the validation and storage stuff
	$id = media_handle_sideload( $file_array, $post_id, $desc );

	// If error storing permanently, unlink
	if ( is_wp_error($id) ) {

print_r($id);
		@unlink($file_array['tmp_name']);
		return $id;
	}

	$src = wp_get_attachment_url( $id );

echo $src;




}

private function add_page_if_needed(){



}

/**
 * On activation, set a time, frequency and name of an action hook to be scheduled.
 */
public function activate_hook() {

wp_clear_scheduled_hook( 'lh_backup_generate' ); 

wp_schedule_event( time(), 'hourly', 'lh_backup_generate' );

wp_clear_scheduled_hook( 'lh_backup_email' ); 

wp_schedule_event( time(), 'daily', 'lh_backup_email' );

$this->add_page_if_needed();

}


public function deactivate_hook() {

wp_clear_scheduled_hook( 'lh_backup_generate' ); 

wp_clear_scheduled_hook( 'lh_backup_email' ); 

}





function create_page(){

global $user_ID;

$page['post_type']    = 'page';
$page['post_parent']  = 0;
$page['post_author']  = $user_ID;
$page['post_status']  = 'private';
$page['post_title']   = 'LH Backup Page';
$pageid = wp_insert_post ($page);

return $pageid;


}


public function run_processes(){

global $wpdb;

if (get_option($this->queue_name)){

$queue = get_option($this->queue_name);

$sql = $this->sanitize_query__string("SELECT ".$queue[0]);

$title = strtolower(sanitize_file_name($sql));

$result = $wpdb->get_results($sql);

$data = print_r($result, true);

$this->create_file($title,$result);

unset($queue[0]);
$queue = array_values($queue);

update_option($this->queue_name,$queue);



}


}



public function email_files() {

if ( get_post_status( $this->options[$this->page_id_field]) ){


$files = get_attached_media('', $this->options[$this->page_id_field]);



$attachments = array();

foreach ($files as $file) {

array_push($attachments, get_attached_file($file->ID));

}




wp_mail($this->options[ $this->email_field ], 'LH Backup scheduled email today', "files that are attached",'',$attachments);


//Remove the files once emailed


    $attachments = get_posts( array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'post_parent'    => $this->options[$this->page_id_field]
    ) );

    foreach ( $attachments as $attachment ) {
        if ( false === wp_delete_attachment( $attachment->ID ) ) {
            // Log failure to delete attachment.
        }
    }


update_option($this->queue_name,$this->options[ $this->queries_field ]);

}



}


public function plugin_menu() {
add_options_page('LH Backup Options', 'LH Backup', 'manage_options', $this->filename, array($this,"plugin_options"));
}


public function plugin_options() {

if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}



    $lh_backup_hidden_field_name = 'lh_backup_submit_hidden';
   
 // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'

    if( isset($_POST[  $lh_backup_hidden_field_name ]) && $_POST[  $lh_backup_hidden_field_name ] == 'Y' ) {
        // Read their posted value

if (get_post_status($_POST[ $this->page_id_field ])){

$options[ $this->page_id_field ]  = sanitize_text_field($_POST[ $this->page_id_field ]);

}

$emails = explode(",", sanitize_text_field($_POST[ $this->email_field ]));

if (is_array($emails)){

$options[ $this->email_field ]  = $this->array_fix($emails);

}


$queries = explode(",", sanitize_text_field($_POST[ $this->queries_field ]));

if (is_array($queries)){

$options[ $this->queries_field ] = $this->array_fix($queries);

}





        // Put an settings updated message on the screen


if (update_option( $this->opt_name, $options )){


$this->options = get_site_option($this->opt_name);

?>
<div class="updated"><p><strong><?php _e('LH Backup Settings Updated', 'menu-test' ); ?></strong></p></div>
<?php


}


}







// settings form

// Now display the settings editing screen
    
?>



<div class="wrap">

<h1>LH Backup Settings</h1>

<form name="form1" method="post" action="">
<input type="hidden" name="<?php echo $lh_backup_hidden_field_name; ?>" value="Y">

<p><label for="<?php echo $this->page_id_field; ?>"><?php _e("Backup Page Id;", 'menu-test' ); ?></label>
<input type="number" name="<?php echo $this->page_id_field; ?>" id="<?php echo $this->page_id_field; ?>" value="<?php echo $this->options[$this->page_id_field]; ?>" size="10" /><a href="<?php echo get_permalink($this->options[$this->page_id_field]); ?>">Link</a>
</p>

<p><label for="<?php echo $this->email_field; ?>"><?php _e("Backup Email Recipients;", 'menu-test' ); ?></label> 
<input type="text" name="<?php echo $this->email_field; ?>" id="<?php echo $this->email_field; ?>" value="<?php echo implode(",", $this->options[ $this->email_field ]); ?>" size="60" /><br/>
Enter a comma separated list of email addresses excluding e.g. *foo@bar.com,you@youremail.net
</p>

<p><label for="<?php echo $this->queries_field; ?>"><?php _e("Queries to Run;", 'menu-test' ); ?></label> 
<input type="text" name="<?php echo $this->queries_field; ?>" id="<?php echo $this->queries_field; ?>" value="<?php echo implode(",", $this->options[ $this->queries_field ]); ?>" size="60" /><br/>
Enter a comma separated list of sql statements excluding the "select" function e.g. * from wp_usermeta,* from wp_users
</p>


<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
</p>

</form>
</div>

<?php

}

// add a settings link next to deactive / edit
public function add_settings_link( $links, $file ) {

	if( $file == $this->filename ){
		$links[] = '<a href="'. admin_url( 'options-general.php?page=' ).$this->filename.'">Settings</a>';
	}
	return $links;
}

    /*
    *  Constructor
    *
    *  @description: This method will be called each time this object is created
    */


function __construct() {

$this->filename = plugin_basename( __FILE__ );

$this->options = get_option($this->opt_name);

//Add the menu

add_action('admin_menu', array($this,"plugin_menu"));

//only required if you need it to run every 15 minutes

//cron the generation of the file and email

add_action( 'lh_backup_generate', array($this,"run_processes"));

add_action( 'lh_backup_email', array($this,"email_files"));

add_filter('plugin_action_links', array($this,"add_settings_link"), 10, 2);


}


}

$lh_backup_instance = new LH_backup_plugin();
register_activation_hook(__FILE__,array($lh_backup_instance,'activate_hook') );
register_deactivation_hook( __FILE__, array($lh_backup_instance,'deactivate_hook') );

function lh_backup_on_uninstall(){

delete_option('lh_backup-options');
delete_option( 'lh_backup-queue');

}


register_uninstall_hook( __FILE__, 'lh_backup_on_uninstall' );



?>