<?php
/*
Plugin Name: NiceSeo.Ru Vkontakte CrossPost
Plugin URI: http://NiceSeo.Ru
Description: Автокросспостинг постов в паблик вконтакте!
Author: Linur
Author URI: http://NiceSeo.Ru
Version: 1.2
Put in /wp-content/plugins/ of your Wordpress installation
*/

$niceseoname = "NiceSEO VKontakte Crossposter Settings";

/* 1. При публикации поста вызываем главную ф-ю niceseo_vk  */ add_action('publish_post', 'niceseo_vk_publish_new');
/* 2. При нажатии кнопки  вызываем главную ф-ю niceseo_vk   */ add_action('wp_ajax_niceseo_vk', 'niceseo_vk_ajax');
/* 3. Добавляем пункт в админ-панель, ф-я niceseo_add_admin */ add_action('admin_menu', 'niceseo_add_admin');  
/* 4. При удалении плагина вызываем ф-ю niceseo_deinstall   */ if (function_exists('register_uninstall_hook')) {register_uninstall_hook(__FILE__, 'niceseo_deinstall');}
/* 5. Доб. ссылки на пост и кнопку в редак.поста в админке    */ add_action('admin_init', 'niceseo_vk_box', 1); 

// 0. Главная ф-ия
function niceseo_vk($post_ID) {
	// 0.0. Получаем исходные данные из настроек скрипта, заданных в админке
	/* 0.0.1 получаем айди юзера	*/ $user_id = get_option('niceseo_opt_userid');
	/* 0.0.2 получаем юзер токен	*/ $access_token = get_option('niceseo_opt_accesstoken');
	/* 0.0.3 получаем айди палика	*/ $gid=get_option('niceseo_opt_gid');
	/* 0.0.4 получаем фотоальбом  	*/ $aid=get_option('niceseo_opt_aid');
	/* 0.0.5 получаем тэги			*/ $tags=get_option('niceseo_opt_tags');$tagsafter=get_option('niceseo_opt_tagsafter');
	/* 0.0.6 Скрытые параметры 		*/ $watermark=false;$tagslug=false;

	// 0.1. Получаем данные из WP
	/* 0.1.1 получаем урл 			*/ $happypost_url = get_permalink($post_ID);
	/* 0.1.2 получаем название 		*/ $happypost_title = get_the_title($post_ID);
	/* 0.1.3 получаем тэги 			*/ if ($tags==1) {$happypost_tags0=get_the_tags($post_ID);$happypost_tags_cou=0;foreach($happypost_tags0 as $tag){if ($tag->name>1950 && $tag->name<2100) {} else {$happypost_tags_cou++;
	if ($tagslug==false){$happypost_tags_array[$happypost_tags_cou]='#'.str_replace(array('-',' '),'_',$tag->name).' ';} else {$happypost_tags_array[$happypost_tags_cou]='#'.str_replace(array('-',' '),'_',$tag->slug).' ';}
	}} shuffle($happypost_tags_array);$happypost_tags_cou=0;foreach($happypost_tags_array as $tag) {if($happypost_tags_cou<4) {$happypost_tags_cou++;$happypost_tags .= $tag;}}$happypost_tags = substr($happypost_tags,0,-1);$happypost_tags_after = explode(" ",$tagsafter);shuffle($happypost_tags_after);foreach ($happypost_tags_after as $tag) {if($happypost_tags_cou<4) {$happypost_tags_cou++;$happypost_tags .= " ".$tag;}}}
	/* 0.1.4 получаем картинку 		*/ $happypost_image = get_post_thumbnail_id($post_ID);$happypost_image = wp_get_attachment_image_src( $happypost_image, 'large' );
	if ($watermark==true) {$happypost_image_temp = file_get_contents(str_replace('/wp-content/uploads/','/h/w.php?i=',$happypost_image[0]));}
	$happypost_image = substr($happypost_image[0],strlen($_SERVER['SERVER_NAME'])+7);$happypost_image = $_SERVER['DOCUMENT_ROOT'].$happypost_image;
	if ($watermark==true) {$happypost_image2 = substr($happypost_image,0,strrpos($happypost_image,'.')).'_vk'.substr($happypost_image,strrpos($happypost_image,'.'));file_put_contents($happypost_image2,$happypost_image_temp);$happypost_image=$happypost_image2;}
	
	// 0.2. Выцепляем из поста soundcloud
	
	// 0.3. Загружаем изображение
	/* 0.3.1 Узнаем серв куда заливать */ $ph_ser = json_decode(file_get_contents("https://api.vk.com/method/photos.getUploadServer?album_id=$aid&group_id=$gid&v=5.2&access_token=$access_token"));
	/* 0.3.2 Заливаем злосчастную фотку*/ $ph_upl = json_decode(curlPost($ph_ser->response->upload_url,array("file1"=>"@".$happypost_image)));
	/* 0.3.3 Сохраняем,добавляем опис. */ $ph_sav = json_decode(file_get_contents("https://api.vk.com/method/photos.save?caption=".urlencode($happypost_title).'%0A%0A'.urlencode($happypost_url)."&album_id=".$ph_upl->aid."&group_id=".$gid."&server=".$ph_upl->server."&photos_list=".$ph_upl->photos_list."&hash=".$ph_upl->hash."&v=5.2&access_token=$access_token"));
	/* 0.3.4 Делаем обложкой альбома   */ $ph_cov = file_get_contents("https://api.vk.com/method/photos.makeCover?owner_id=-".$gid."&photo_id=".$ph_sav->response[0]->id."&album_id=".$aid."&v=5.2&access_token=$access_token");
	/* 0.3.5 Узнаём id данной фотки    */ $ph_id = $ph_sav->response[0]->id;
	
	/* 0.4. Постим на стене */ 
	/* 0.4.1 Формируем запрос, начало  */ $st_zap = "https://api.vk.com/method/wall.post?owner_id=-".$gid."&friends_only=0&message=".urlencode($happypost_title).($tags==1 ? '%0A%0A'.urlencode($happypost_tags) : '')."&attachments=";
	/* 0.4.1.2 Добавляем изображение   */ if (strlen($ph_id)>1) {$st_zap.= "photo-".$gid."_".$ph_id.",";}
	/* 0.4.1.3 Добавляем soundcloud    */ if (strlen($au_id)>1) {$st_zap.= "audio-".$gid."_".$au_id.",";}
	/* 0.4.1.4 Формируем запрос, конец */ $st_zap.= $happypost_url."&v=5.2&access_token=$access_token";
	/* 0.4.2 Постим                    */ $st_res = json_decode(file_get_contents(str_replace(' ', '%20', $st_zap)));
	/* 0.4.3 Узнаём id данного поста   */ $st_id = $st_res->response->post_id;
	
	/* 0.5.1 Ставим лайк на стене      */ $li_st = file_get_contents("https://api.vk.com/method/likes.add?type=post&owner_id=-".$gid."&item_id=".$st_id."&v=5.2&access_token=$access_token");
	/* 0.5.2 Ставим лайк к фотке       */ $li_ph = file_get_contents("https://api.vk.com/method/likes.add?type=photo&owner_id=-".$gid."&item_id=".$ph_id."&v=5.2&access_token=$access_token");
	/* 0.5.3 Ставим лайк странице сайта*/ $li_si = file_get_contents("https://api.vk.com/method/likes.add?type=sitepage&owner_id=-".$gid."&item_id=".$happypost_url."&v=5.2&access_token=$access_token");
	
	/* 0.6.1 Добав в вп ссылку на пост */ if (strlen($st_id)>1) {add_post_meta($post_ID,'niceseovklink',$gid.'_'.$st_id);}
	/* 0.6.2 Добав в вп ссылку на audio*/ if (strlen($au_id)>1) {add_post_meta($post_ID,'niceseovkaudio',$au_id);}
	/* 0.6.3 Добав в вп ссылку на фотку*/ if (strlen($ph_id)>1) {add_post_meta($post_ID,'niceseovkphoto',$ph_id);}
}

// 1. Ф-я постинга при публикации новой записи
function niceseo_vk_publish_new($post_ID) {$nicel=get_post_meta($post_ID,'niceseovklink');if (count($nicel)==0) {if(($_POST['post_status']=='publish') && ($_POST['original_post_status']!='publish')) {/*!*/niceseo_vk($post_ID);/*!*/}}}

// 2. Ф-я постинга при нажатии кнопки запостить
function niceseo_vk_ajax(){$post_ID=$_REQUEST['post_id'];if(get_post_status($post_ID)=='publish'){/*!*/niceseo_vk($post_ID);/*!*/}header ("Location: http://".$_SERVER['HTTP_HOST']."/wp-admin/post.php?post=".$post_ID."&action=edit");exit;}

// 3. Функция настроек в админ-панели
function niceseo_add_admin() {global $niceseoname;add_options_page(__('Settings').': '.$niceseoname, $niceseoname, 'edit_themes', basename(__FILE__), 'niceseo_to_admin');}
function niceseo_to_admin() {global $niceseoname;echo '<div class="wrap">';screen_icon();echo '<h2>'.__('Settings').': '.$niceseoname.'</h2>';if (isset($_POST['save'])) {/* обработка запроса */
	update_option('niceseo_opt_userid', stripslashes($_POST['niceseo_opt_userid']));
	update_option('niceseo_opt_accesstoken', stripslashes($_POST['niceseo_opt_accesstoken']));
	update_option('niceseo_opt_gid', stripslashes($_POST['niceseo_opt_gid']));
	update_option('niceseo_opt_aid', stripslashes($_POST['niceseo_opt_aid']));
	if (isset($_POST['niceseo_opt_tags'])) {update_option('niceseo_opt_tags', 1);} else {update_option('niceseo_opt_tags', 0);}
	update_option('niceseo_opt_tagsafter', stripslashes($_POST['niceseo_opt_tagsafter']));
	echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><b>'.__('Settings saved.').'</b></p></div>';} ?><form method="post"><table class="form-table"><tr valign="top"><th scope="row">Id вашего аккаунта Вконтакте (например: <strong>2177397</strong>):</th><td><input name="niceseo_opt_userid" class="regular-text" type="text" value="<?php echo get_option('niceseo_opt_userid'); ?>" ></td></tr><tr valign="top"><th scope="row">Ваш access_token (многоцифрбукв):</th><td><input name="niceseo_opt_accesstoken" class="regular-text" type="text" value="<?php echo get_option('niceseo_opt_accesstoken'); ?>" ></td></tr><tr valign="top"><th scope="row">Id паблика, в который постить (например: <strong>42801106</strong>):</th><td><input name="niceseo_opt_gid" class="regular-text" type="text" value="<?php echo get_option('niceseo_opt_gid'); ?>" ></td></tr><tr valign="top"><th scope="row">Id альбома в паблике (например: <strong>162160701</strong>):</th><td><input name="niceseo_opt_aid" class="regular-text" type="text" value="<?php echo get_option('niceseo_opt_aid'); ?>" ></td></tr><tr valign="top"><th scope="row">Нужно ли постить тэги как хэштэги:</th><td><fieldset><legend class="screen-reader-text"><span>Нужно ли постить тэги как хэштэги</span></legend><label for="niceseo_opt_tags"><input name="niceseo_opt_tags" type="checkbox" value="1" <?php if(get_option('niceseo_opt_tags')==1) { echo 'checked="checked"'; } ?>> Если да, поставьте здесь галочку</label></fieldset></td></tr><tr valign="top"><th scope="row">Добавить еще тэги для всех записей:</th><td><input name="niceseo_opt_tagsafter" class="regular-text" type="text" value="<?php echo get_option('niceseo_opt_tagsafter'); ?>" ></td></tr></table><div class="submit"><input name="save" type="submit" class="button-primary" value="<?php echo __('Save Draft'); ?>" /></div></form></div><?php }

// 4. Функция удаления следов плагина при деинсталляции
function niceseo_deinstall() {delete_option('niceseo_opt_userid');delete_option('niceseo_opt_accesstoken');delete_option('niceseo_opt_gid');delete_option('niceseo_opt_aid');delete_option('niceseo_opt_tags');delete_option('niceseo_opt_tagsafter');}

// 5. Доб. ссылки на твит и картинку в редак.поста в админке
function niceseo_vk_box() {add_meta_box('niceseo_vk_box','NiceSeo VKontakte Crossposter','niceseo_vk_box_inner','post','side');}
function niceseo_vk_box_inner($post) {
	$niceseo_vk_box_link=get_post_meta($post->ID,'niceseovklink');
	$niceseo_vk_box_image=get_post_meta($post->ID,'niceseovkphoto');
	$niceseo_vk_box_audio=get_post_meta($post->ID,'niceseovkaudio');
	if (count($niceseo_vk_box_link)>0) {echo '<p>Посты ВК данного поста<strong> ('.count($niceseo_vk_box_link).'):</strong></p>';$i=0;while($i<count($niceseo_vk_box_link)) {echo '<p><a target="_blank" href="//vk.com/wall-'.$niceseo_vk_box_link[$i].'">wall-'.$niceseo_vk_box_link[$i].'</a>';
	if(strlen($niceseo_vk_box_audio[$i]>1)){echo ' (Трек <a target="_blank" href="//vk.com/audio-'.get_option('niceseo_opt_gid').'_'.$niceseo_vk_box_audio[$i].'">запостился</a>!)';}
	if(strlen($niceseo_vk_box_image[$i]>1)){echo ' (Фотка <a target="_blank" href="//vk.com/photo-'.get_option('niceseo_opt_gid').'_'.$niceseo_vk_box_image[$i].'">запостилась</a>!)';}
	echo '</p>';$i++;} } else {echo '<p>Постов ВК пока нет!</p>';} 
	$link = admin_url('admin-ajax.php?action=niceseo_vk&post_id='.$post->ID);
	if(get_post_status($post->ID)=='publish') {echo '<a id="niceseovk" class="button button-primary button-large" style="margin-top:0" href="'.$link.'">Запостить ВК!</a>';}
}

/* Функция cURL (вспомогательная для основной) */ function curlPost($url, $data=array()) {if ( ! isset($url)) {return false;}$ch = curl_init();curl_setopt($ch, CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);curl_setopt($ch, CURLOPT_TIMEOUT, 10);curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);curl_setopt($ch, CURLOPT_URL, $url);if (count($data) > 0) {curl_setopt($ch, CURLOPT_POST, true);curl_setopt($ch, CURLOPT_POSTFIELDS, $data);}$response = curl_exec($ch);curl_close($ch);return $response;}

?>