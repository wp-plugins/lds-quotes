<?php
/*
Plugin Name: LDS Quotes
Plugin URI: http://www.mattlsmith.com
Description: LDS Quotes 
Version: 2.0.3
Author: Matt Smith 
*/

register_activation_hook(__FILE__,'quotesInstall');

add_action('admin_menu', 'quotesMenu');
add_action('widgets_init', 'quotesRegisterWidget');

add_filter("plugin_row_meta", 'quotesPluginLinks', 10, 2);

add_shortcode('quote', 'ldsQuotes');
add_shortcode('bomQuote', 'ldsQuotes');  
add_shortcode('ldsQuote', 'ldsQuotes');  

function quotesInstall() {

	if(!function_exists('curl_init')) {
		echo 'Unable to use CURL, install abandoned';
		exit;
	}

	quotesUpdateCategories();
}

function quotesPluginLinks($links, $file) { 

	$plugin = plugin_basename(__FILE__);

	if($file == $plugin) {

		$links[] = '<a href="options-general.php?page=lds-quotes">Status</a>';
		$links[] = '<a target="_BLANK" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZHV7QFM84SL8A">Donate</a>';
	}
	return $links;
}

function quotesSettings() {

	if (!is_admin()) {
		return;
	}

	if(isset($_REQUEST['updateCategory'])) {

		quotesUpdateCategories();
	}

	if(isset($_REQUEST['selectCategory'])){

		quotesUpdateSelectedCategories($_REQUEST['category']);
	}

	if(isset($_REQUEST['disableLinks'])) {
		quotesAllowLinks(false);
	}
	
	if(isset($_REQUEST['enableLinks'])) {
		quotesAllowLinks(true);
	}

	$xml = @simplexml_load_string(get_option('quotesCategories'));

	echo "<h2>Instruction for use</h2>";
	?>

	There are a few ways you can use this plugin on your website. The easiest<br/>
	way to get started is by embedding a simple piece of code that will pull up <br/>
	the LDS quotes anywhere you want. To add this code simple paste <br/>
	[bomQuote] into any page or widget and the quotes will begin to display.<br/><br/><br/>

	Use Short Code :<b> [ldsQuote] </b><br/>
	Use Method Calling :<b>&lt;?php ldsQuotes() ;?&gt; </b></br>
	Use Do Short Code : <b>&lt;?php echo do_shortcode("[ldsQuote]"); ?&gt;</b></br></br>

	<label style="font-weight:bold;font-size:17px;">Select Which Categories you Want to Display Quotes From</label><br/><br/>
	<form method="post" action="">

	<?php

	if($xml) {

		$selectedCategories = quotesGetSelectedCategories();

		foreach($xml->children() as $child) {

			?>

			<div style="font-weight:bold;padding-bottom:5px;">

			<input type="checkbox" name="category[]" id="<?php echo $child;?>"
			  <?php if (in_array($child, $selectedCategories)) echo "checked='checked'";?>
			  value='<?php echo $child;?>'/>
			
			  &nbsp;
			  <label for="<?php echo $child; ?>"><?php echo $child;?></label>

			</div>

			<?php

		}
	}

	?>

	<br/>
	<input type="submit" value="Update Quote Categories" name="selectCategory"/>
	<input type="submit" value="Refresh Category List" name="updateCategory" />
	<br />
	<br />

	<?php
	if(quotesAllowLinks()) {
		echo '<input type="submit" value="Turn Off Quote Links" name="disableLinks" />';
	} else {
		echo '<input type="submit" value="Turn On Quote Links" name="enableLinks" />';	
	}
	?>
	
	<br/><br/>
	Enabling quote links will insert a link to http://www.mormon.org in the widget title <br />
	in addition to linking the quote author to the scripture or conference talk on http://www.lds.org.
	
	</form>

	<?php

}

function ldsQuotes() {

	$selectedCategories = quotesGetSelectedCategories();

	if($quote = quotesGetCached($selectedCategories)) {
		print_r($quote);
		return;
	}

	$url="http://quote.mattlsmith.com/get_quote.php?qu=" . urlencode(implode(':', $selectedCategories)) . '&allow_links=' . (quotesAllowLinks() ? '1' : '0') . '&domain=' . urlencode($_SERVER['SERVER_NAME']);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 4);
	
	$output = curl_exec($ch);

	curl_close($ch);

	if($output !== false) {	

		print_r($output);
		quotesSaveCache($selectedCategories, $output);
	}
}

function quotesMenu() {

	add_options_page('LDS Quote Options', 'LDS Quotes', 'manage_options', 'lds-quotes', 'quotesSettings');
}

class quotesWidget extends WP_Widget {

	function quotesWidget() {
		// Instantiate the parent object
		parent::__construct(false, 'LDS Quotes');
	}

	function widget($args, $instance) {
		
		// Widget output		
		if(quotesAllowLinks()) {
			$a = '<a href="http://www.mormon.org">';
			$b = '</a>';
		}

		echo "<div id=\"text-3\" class=\"widget widget_text\"><h3 class=\"widget-title\">{$a}LDS Quotes{$b}</h3><div class=\"textwidget\">";
		
		echo ldsQuotes();
		echo '</div></div>';
	}

	function update($new_instance, $old_instance) {

		// Save widget options
	}

	function form($instance) {
		echo '<a href="options-general.php?page=lds-quotes">Quote Options</a>';
		// Output admin widget options form
	}
}

function quotesRegisterWidget() {
	register_widget('quotesWidget');
}

function quotesGetSelectedCategories() {

        $selected = get_option('quotesSelectedCategories');
        return is_array($selected) ? $selected : array();
}

function quotesUpdateSelectedCategories($categories) {

	update_option('quotesSelectedCategories', $categories);
}

function quotesUpdateCategories() {

	$url="http://quote.mattlsmith.com/update.php?qu=&site=";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$output = curl_exec($ch);

	update_option('quotesCategories', $output);

}

function quotesGetCached($selectedCategories) {

	$time = get_option('quoteCachedTime' . serialize($selectedCategories));
	
	if($time > time() - 60*5) {
		return get_option('quoteCached' . serialize($selectedCategories));
	}

	return false;
}

function quotesSaveCache($selectedCategories, $output) {
 
	update_option('quoteCached' . serialize($selectedCategories), $output);
	update_option('quoteCachedTime' . serialize($selectedCategories), time());	
}

function quotesAllowLinks($update = -1) {

	if($update === -1) {
		return get_option('quoteShowLink') ? true : false;
	}

	update_option('quoteShowLink', $update ? true : false);
	
}
?>