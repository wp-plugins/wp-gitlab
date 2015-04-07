<div class="wrap">
<h2>WP Gitlab</h2>

<form method="post" action="options.php">
    <?php settings_fields('wp-gitlab'); ?>
    <?php do_settings_sections('wp-gitlab'); ?>
    <table class="form-table">
        <tr valign="top">
			<th scope="row">Cache Time</th>
			<td>
				<input type="text" name="wpgitlab_cache_time" value="<?php echo get_option('wpgitlab_cache_time', 600); ?>" />
				<p class="description">This value goes in seconds. For example: 600 seconds is 10 minutes.</p>
			</td>
        </tr>
    </table>
    <p></p>
    <table class="form-table">
        <tr valign="top">
			<th scope="row">Gitlab URL</th>
			<td>
				<input type="text" name="wpgitlab_url" value="<?php echo get_option('wpgitlab_url', 'URL'); ?>" />
				<p class="description">URL to your gitlab server</p>
			</td>
        </tr>
    </table>
    <p></p>
    <table class="form-table">
        <tr valign="top">
			<th scope="row">Gitlab API Key</th>
			<td>
				<input type="text" name="wpgitlab_api_key" value="<?php echo get_option('wpgitlab_api_key', 'API Key'); ?>" />
				<p class="description">API key to your gitlab installation</p>
			</td>
        </tr>
    </table>
	<p></p>	
	<table class="form-table">
		<tr valign="top">
			<th scope="row">Clear Cache</th>
			<td>
				<label>
					<input type="checkbox" name="wpgitlab_clear_cache" value="1" /> Delete all data retrieved and saved from Github.
				</label>
			</td>
        </tr>
    </table>
    <?php submit_button(); ?>

</form>
</div>