<?php
/**
 * Plugin Name: Pay-Per-Post Unlocker
 * Description: Protect portions of posts and let visitors pay small fees via Paystack to unlock them.
 * Version: 1.0.0
 * Author: Collins Kulei
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Activation: create transactions table
register_activation_hook( __FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'ppp_transactions';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, post_id bigint(20) unsigned NOT NULL, email varchar(191), reference varchar(191), amount bigint(20) unsigned NOT NULL, created_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id)) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
});

// Settings page
add_action('admin_menu', function(){
    add_options_page('Pay-Per-Post', 'Pay-Per-Post', 'manage_options', 'ppp-settings', function(){
        if (isset($_POST['ppp_save'])) {
            update_option('ppp_public_key', sanitize_text_field($_POST['ppp_public_key']));
            update_option('ppp_secret_key', sanitize_text_field($_POST['ppp_secret_key']));
            update_option('ppp_currency', sanitize_text_field($_POST['ppp_currency']));
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        ?>

```
    <div class="wrap">
    <h1>Pay-Per-Post Unlocker</h1>
    <form method="post">
        <table class="form-table">
            <tr><th>Paystack Public Key</th><td><input type="text" name="ppp_public_key" value="<?php echo esc_attr(get_option('ppp_public_key')); ?>" class="regular-text"/></td></tr>
            <tr><th>Paystack Secret Key</th><td><input type="password" name="ppp_secret_key" value="<?php echo esc_attr(get_option('ppp_secret_key')); ?>" class="regular-text"/></td></tr>
            <tr><th>Currency</th><td><input type="text" name="ppp_currency" value="<?php echo esc_attr(get_option('ppp_currency','KES')); ?>" class="small-text"/></td></tr>
        </table>
        <p><input type="submit" name="ppp_save" class="button-primary" value="Save Settings"></p>
    </form>
    <p>Use shortcode: <code>[ppp_protect price="100" label="Unlock for KES 100"]Secret content[/ppp_protect]</code></p>
    </div>
    <?php
});
```

});

// REST endpoints for Paystack init + verify
add_action('rest_api_init', function(){
register_rest_route('ppp/v1','/init',array('methods'=>'POST','callback'=>function($req){
$p=$req->get_json_params();$email=sanitize_email($p['email']);$amount=intval($p['amount']);$post=intval($p['post_id']);
if(!$email||!$amount||!$post)return new WP_Error('invalid','Missing params',array('status'=>400));
$body=array('email'=>$email,'amount'=>$amount*100,'metadata'=>array('post_id'=>$post));
$r=wp_remote_post('[https://api.paystack.co/transaction/initialize',array(](https://api.paystack.co/transaction/initialize',array%28)
'headers'=>array('Authorization'=>'Bearer '.get_option('ppp_secret_key'),'Content-Type'=>'application/json'),
'body'=>wp_json_encode($body),
));
return rest_ensure_response(json_decode(wp_remote_retrieve_body($r),true));
},'permission_callback'=>'__return_true'));

```
register_rest_route('ppp/v1','/verify',array('methods'=>'POST','callback'=>function($req){
    global $wpdb;$p=$req->get_json_params();$ref=sanitize_text_field($p['reference']);$post=intval($p['post_id']);
    $r=wp_remote_get('https://api.paystack.co/transaction/verify/'.$ref,array('headers'=>array('Authorization'=>'Bearer '.get_option('ppp_secret_key'))));
    $d=json_decode(wp_remote_retrieve_body($r),true);
    if(isset($d['data']['status'])&&$d['data']['status']==='success'){
        $wpdb->insert($wpdb->prefix.'ppp_transactions',array('post_id'=>$post,'email'=>$d['data']['customer']['email'],'reference'=>$ref,'amount'=>$d['data']['amount']));
        setcookie('ppp_access_'.$post,$ref,time()+DAY_IN_SECONDS*7,COOKIEPATH,COOKIE_DOMAIN);
        return rest_ensure_response(array('status'=>'success'));
    }
    return new WP_Error('fail','Verification failed',array('status'=>400));
},'permission_callback'=>'__return_true'));
```

});

// Shortcode to protect content
add_shortcode('ppp_protect',function($a,$c=''){
$a=shortcode_atts(array('price'=>'100','label'=>'Unlock content','duration'=>'7'),$a,'ppp_protect');
global $post;$pid=$post->ID;$cookie='ppp_access_'.$pid;
if(isset($_COOKIE[$cookie]))return '<div class="ppp-unlocked">'.do_shortcode($c).'</div>';
ob_start(); ?> <div class="ppp-locked"> <p>This content is locked. Pay to unlock.</p> <button class="ppp-checkout" data-price="<?php echo esc_attr($a['price']); ?>" data-post="<?php echo esc_attr($pid); ?>" data-label="<?php echo esc_attr($a['label']); ?>"> <?php echo esc_html($a['label']); ?> </button> <script src="https://js.paystack.co/v1/inline.js"></script> </div>
<?php return ob_get_clean();
});

// JS enqueue
add_action('wp_enqueue_scripts',function(){
wp_enqueue_script('ppp-js','[https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js',array(),null,true](https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js',array%28%29,null,true));
wp_add_inline_script('ppp-js',"jQuery(document).on('click','.ppp-checkout',function(e){e.preventDefault();var b=jQuery(this);var email=prompt('Enter your email');if(!email)return;fetch('".rest_url('ppp/v1/init')."',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:email,amount:b.data('price'),post_id:b.data('post')})}).then(r=>r.json()).then(d=>{if(d.data&&d.data.authorization_url){var handler=PaystackPop.setup({key:'".get_option('ppp_public_key')."',email:email,amount:b.data('price')*100,ref:d.data.reference,callback:function(res){fetch('".rest_url('ppp/v1/verify')."',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({reference:res.reference,post_id:b.data('post')})}).then(r=>r.json()).then(v=>{if(v.status==='success')location.reload();});}});handler.openIframe();}else alert('Init failed');});});");
});
