<?php
/**
 * Plugin Name: WP Gitlab
 * Plugin URI: https://git.cs90.co/cfarence/wp-gitlab
 * Description: Display users Gitlab public repositories, commits, issues and gists.
 * Author: Charles Farence
 * Author URI: http://www.charlessite90.com
 * Version: 1.0
 *
 * Licensed under the MIT License
 */
require dirname(__FILE__) . '/lib/cache.php';
require(dirname(__FILE__) . '/lib/gitlab.php');

// Init General Style
add_action('wp_enqueue_scripts', 'wpgitlab_style', 20);
function wpgitlab_style(){
	wp_enqueue_style('wp-gitlab', plugin_dir_url(__FILE__).'wp-gitlab.css');
	
	// If custom stylesheet exists load it.
	$custom = plugin_dir_path( __FILE__ ).'custom.css';
	if(file_exists($custom)){
		wp_enqueue_style('wp-gitlab-custom', plugin_dir_url(__FILE__).'custom.css');
	}
}

// Admin 
add_action('admin_menu','wpgitlab_plugin_menu');
add_action('admin_init', 'wpgitlab_register_settings' );

function wpgitlab_plugin_menu(){
    add_options_page('WP Gitlab Options', 'WP Gitlab', 'manage_options', 'wp-gitlab', 'wpgitlab_plugin_options');
}

function wpgitlab_register_settings() {
	//register our settings
	register_setting('wp-gitlab', 'wpgitlab_cache_time', 'wpgitlab_validate_int');
	register_setting('wp-gitlab', 'wpgitlab_clear_cache', 'wpgitlab_clearcache' );
	register_setting('wp-gitlab', 'wpgitlab_url', 'wpgitlab_hosturl' );
	register_setting('wp-gitlab', 'wpgitlab_api_key', 'wpgitlab_validate_api' );
} 

function wpgitlab_validate_api($input) {
	return $input;
}

function wpgitlab_plugin_options(){
    include('admin/options.php');
}

function wpgitlab_clearcache($input){
	if($input == 1){
		foreach(glob(plugin_dir_path( __FILE__ )."cache/*.json") as $file){
			unlink($file);
		}
		add_settings_error('wpgitlab_clear_cache',esc_attr('settings_updated'),'Cache has been cleared.','updated');
	}
}

function wpgitlab_hosturl($input) {
	return $input;
}

function wpgitlab_validate_int($input) {
	return intval($input); // return validated input
}

add_action('widgets_init', 'register_gitlab_widgets');

function register_gitlab_widgets(){
	register_widget('Gitlab_Widget_Profile');
	register_widget('Gitlab_Widget_Repos');
	register_widget('Gitlab_Widget_Commits');
	register_widget('Gitlab_Widget_Issues');
}

/*
 * Profile widget.
 */
class Gitlab_Widget_Profile extends WP_Widget{
	function Gitlab_Widget_Profile() {
		$widget_ops = array('description' => __('Displays the gitlab user profile.'));           
        $this->WP_Widget(false, __('Gitlab Profile'), $widget_ops);
	}
	
	function form($instance) {
		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		
		?>
	    	<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" 
					name="<?php echo $this->get_field_name('title'); ?>" type="text" 
					value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('gitlab Username:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" 
					name="<?php echo $this->get_field_name('username'); ?>" type="text" 
					value="<?php echo $username; ?>" />
			</p>
	    <?php
	}
	
	function widget($args, $instance) {
		extract($args);	
		
		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		$gitlaburl = get_option('wpgitlab_url', null);

		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		// Init the cache system.
		$cache = new Gitlab_Cache();
		// Set custom timeout in seconds.
		$cache->timeout = get_option('wpgitlab_cache_time', 600);
			
		$profile = $cache->get($username . '.json');
		if($profile == null) {
			$gitlab = new gitlab($username);
			$profile = $gitlab->get_profile();
			$cache->set($username . '.json', $profile);
		}
		
		$profileurl = $gitlaburl.'/u/'.$username;
		
		if($profile->bio == "") {
			$profile->bio = "No Bio :(";
		}
		
		echo '<div class="wpgitlab-profile">';
		echo '<div class="wpgitlab-user">';
		echo '<a href="'. $profileurl . '" title="View ' . $username . '\'s gitlab"><img src="'. $profile->avatar_url . '" alt="View ' . $username . '\'s gitlab" height="56" width="56" /></a>';
		echo '<h2 class="wpgitlab-username"><a href="'. $profileurl . '" title="View ' . $username . '\'s gitlab">' . $username . '</a></h2>';
		echo '<p class="wpgitlab-name">' . $profile->name . '</p>';
		echo '<p class="wpgitlab-name">'.$profile->bio.'</p>';
		echo '</div>';
		echo '<a class="wpgitlab-bblock" href="'.$profileurl.'"' . $username . '?tab=repositories"><span class="wpgitlab-count">'.$profile->number_repo.'</span><span class="wpgitlab-text">Public Repos</span></a>';
		echo '</div>';
		
		echo $after_widget;
	}
	
	private function get_title($instance) {
		return empty($instance['title']) ? 'My gitlab Profile' : apply_filters('widget_title', $instance['title']);
	}
	
	private function get_username($instance) {
		return empty($instance['username']) ? 'cfarence' : $instance['username'];
	}
}

/*
 * Repositories widget.
 */
class Gitlab_Widget_Repos extends WP_Widget{
	function Gitlab_Widget_Repos() {
		$widget_ops = array('description' => __('Displays the repositories from a specific user.'));           
        $this->WP_Widget(false, __('Gitlab Repositories'), $widget_ops);
	}
	
	function form($instance) {
		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		$project_count = $this->get_project_count($instance);

		?>
	    	<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" 
					name="<?php echo $this->get_field_name('title'); ?>" type="text" 
					value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('gitlab Username:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" 
					name="<?php echo $this->get_field_name('username'); ?>" type="text" 
					value="<?php echo $username; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('project_count'); ?>"><?php _e('Number of projects to show:'); ?> </label>
					<input id="<?php echo $this->get_field_id('project_count'); ?>" 
					name="<?php echo $this->get_field_name('project_count'); ?>" type="text" 
					value="<?php echo $project_count; ?>" size="3" />
			</p>
	    <?php
	}
	
	function widget($args, $instance) {
		extract($args);	
		
		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		$project_count = $this->get_project_count($instance);

		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		// Init the cache system.
		$cache = new Gitlab_Cache();
		// Set custom timeout in seconds.
		$cache->timeout = get_option('wpgitlab_cache_time', 600);
		
		$repositories = $cache->get($username . '.repositories.json');
		if($repositories == null) {
			$gitlab = new gitlab($username);
			$repositories = $gitlab->get_repositories();
			$cache->set($username . '.repositories.json', $repositories);
		}

		if($repositories == null || count($repositories) == 0) {
			echo $username . ' does not have any public repositories.';
		} else {
			$repositories = array_slice($repositories, 0, $project_count);
			echo '<ul>';
			foreach($repositories as $repository){
		 		echo '<li><a href="'. $repository->web_url . '" title="'.$repository->description.'">' . $repository->name . '</a></li>';
			}
			echo '</ul>';
		}
		
		echo $after_widget;
	}
	
	private function get_title($instance) {
		return empty($instance['title']) ? 'My gitlab Projects' : apply_filters('widget_title', $instance['title']);
	}
	
	private function get_username($instance) {
		return empty($instance['username']) ? 'cfarence' : $instance['username'];
	}
	
	private function get_project_count($instance) {
		return empty($instance['project_count']) ? 5 : $instance['project_count'];
	}
}

/*
 * Commits widget.
 */
class Gitlab_Widget_Commits extends WP_Widget{
	function Gitlab_Widget_Commits() {
		$widget_ops = array('description' => __('Displays latests commits from a gitlab repository.'));           
        $this->WP_Widget(false, __('Gitlab Commits'), $widget_ops);
	}
	
	function form($instance) {
		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		$repository = $this->get_repository($instance);
		$commit_count = $this->get_commit_count($instance);

		?>
	    	<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" 
					name="<?php echo $this->get_field_name('title'); ?>" type="text" 
					value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('gitlab Username:'); ?> </label>
				<input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" 
					name="<?php echo $this->get_field_name('username'); ?>" type="text" 
					value="<?php echo $username; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('repository'); ?>"><?php _e('gitlab Repository:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('repository'); ?>" 
					name="<?php echo $this->get_field_name('repository'); ?>" type="text" 
					value="<?php echo $repository; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('commit_count'); ?>"><?php _e('Number of commits to show:'); ?> </label>
					<input id="<?php echo $this->get_field_id('commit_count'); ?>" 
					name="<?php echo $this->get_field_name('commit_count'); ?>" type="text" 
					value="<?php echo $commit_count; ?>" size="3" />
			</p>
	    <?php
	}
	
	function widget($args, $instance) {
		extract($args);	

		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		$repository = $this->get_repository($instance);
		$commit_count = $this->get_commit_count($instance);
		
		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		// Init the cache system.
		$cache = new Gitlab_Cache();
		// Set custom timeout in seconds.
		$cache->timeout = get_option('wpgitlab_cache_time', 600);
		
		$commits = $cache->get($username . '.' . $repository . '.commits.json');
		if($commits == null) {
			$gitlab = new Gitlab($username, $repository);
			$commits = $gitlab->get_commits();
			$cache->set($username . '.' . $repository . '.commits.json', $commits);
		}
		
		if($commits == null || count($commits) == 0) {
			echo $username . ' does not have any public commits.';
		} else {
			$commits = array_slice($commits, 0, $commit_count);
			echo '<ul>';
			foreach($commits as $commit){
		 		echo '<li><a href="' . $commit->web_url . '" title="' . $commit->message . '">' . $commit->message . '</a></li>';
			}
			echo '</ul>';
		}
		
		echo $after_widget;
	}
	
	private function get_title($instance) {
		return empty($instance['title']) ? 'My gitlab Commits' : apply_filters('widget_title', $instance['title']);
	}
	
	private function get_username($instance) {
		return empty($instance['username']) ? 'cfarence' : $instance['username'];
	}
	
	private function get_repository($instance) {
		return $instance['repository'];
	}
	
	private function get_commit_count($instance) {
		return empty($instance['commit_count']) ? 5 : $instance['commit_count'];
	}
}

/*
 * Issues widget.
 */
class Gitlab_Widget_Issues extends WP_Widget{
	function Gitlab_Widget_Issues() {
		$widget_ops = array('description' => __('Displays latests issues from a gitlab repository.'));           
        $this->WP_Widget(false, __('Gitlab Issues'), $widget_ops);
	}
	
	function form($instance) {
		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		$repository = $this->get_repository($instance);
		$issue_count = $this->get_issue_count($instance);

		?>
	    	<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" 
					name="<?php echo $this->get_field_name('title'); ?>" type="text" 
					value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('gitlab Username:'); ?> </label>
				<input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" 
					name="<?php echo $this->get_field_name('username'); ?>" type="text" 
					value="<?php echo $username; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('repository'); ?>"><?php _e('gitlab Repository:'); ?> </label>
					<input class="widefat" id="<?php echo $this->get_field_id('repository'); ?>" 
					name="<?php echo $this->get_field_name('repository'); ?>" type="text" 
					value="<?php echo $repository; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('issue_count'); ?>"><?php _e('Number of issues to show:'); ?> </label>
					<input id="<?php echo $this->get_field_id('issue_count'); ?>" 
					name="<?php echo $this->get_field_name('issue_count'); ?>" type="text" 
					value="<?php echo $issue_count; ?>" size="3" />
			</p>
	    <?php
	}
	
	function widget($args, $instance) {
		extract($args);	
		
		$title = $this->get_title($instance);
		$username = $this->get_username($instance);
		$repository = $this->get_repository($instance);
		$issue_count = $this->get_issue_count($instance);
		
		echo $before_widget;
		echo $before_title . $title . $after_title;
		
		// Init the cache system.
		$cache = new Gitlab_Cache();
		// Set custom timeout in seconds.
		$cache->timeout = get_option('wpgitlab_cache_time', 600);
		
		$issues = $cache->get($username . '.' . $repository . '.issues.json');
		if($issues == null) {
			$gitlab = new gitlab($username, $repository);
			$issues = $gitlab->get_issues();
			$cache->set($username . '.' . $repository . '.issues.json', $issues);
		}
		
		if($issues == null || count($issues) == 0) {
			echo $username . ' does not have any public issues.';
		} else {
			$issues = array_slice($issues, 0, $issue_count);
			echo '<ul>';
			foreach($issues as $issue){
		 		echo '<li><a href="' . $issue->web_url . '" title="' . $issue->title . '">' . $issue->title . '</a></li>';
			}
			echo '</ul>';
		}
		
		echo $after_widget;
	}
	
	private function get_title($instance) {
		return empty($instance['title']) ? 'My gitlab Issues' : apply_filters('widget_title', $instance['title']);
	}
	
	private function get_username($instance) {
		return empty($instance['username']) ? 'cfarence' : $instance['username'];
	}
	
	private function get_repository($instance) {
		return $instance['repository'];
	}
	
	private function get_issue_count($instance) {
		return empty($instance['issue_count']) ? 5 : $instance['issue_count'];
	}
}


/*
 * Profile shortcode.
 */
function glprofile_shortcode($atts) {
	extract( shortcode_atts(
		array(
			'username' => 'cfarence'
		), $atts )
	);
	
	// Init the cache system.
	$cache = new Gitlab_Cache();
	// Set custom timeout in seconds.
	$cache->timeout = get_option('wpgitlab_cache_time', 600);
	$gitlaburl = get_option('wpgitlab_url', null);
	$profileurl = $gitlaburl.'/u/'.$username;
		
	$profile = $cache->get(username . '.json');
	if($profile == null) {
		$gitlab = new gitlab($username);
		$profile = $gitlab->get_profile();
		$cache->set($username . '.json', $profile);
	}
	
	if($profile->bio == "") {
			$profile->bio = "No Bio :(";
		}
	
	$html = '<div class="wpgitlab-profile">';
	$html .=  '<div class="wpgitlab-user">';
	$html .=  '<a href="'. $profileurl . '" title="View ' . $username . '\'s gitlab"><img src="'. $profile->avatar_url . '" alt="View ' . $username . '\'s gitlab" height="56" width="56" /></a>';
	$html .=  '<h2 class="wpgitlab-username"><a href="'. $profile->html_url . '" title="View ' . $username . '\'s gitlab">' . $username . '</a></h2>';
	$html .=  '<p class="wpgitlab-name">' . $profile->name . '</p>';
	$html .=  '<p class="wpgitlab-name">' . $profile->bio . '</p>';
	$html .=  '</div>';
	$html .=  '<a class="wpgitlab-bblock" href="https://gitlab.com/' . $username . '?tab=repositories"><span class="wpgitlab-count">'.$profile->number_repo.'</span><span class="wpgitlab-text">Public Repos</span></a>';
	$html .= '</div>';
	return $html;
}
add_shortcode('gitlab-profile', 'glprofile_shortcode');

/*
 * Repositories shortcode.
 */
function glrepos_shortcode($atts) {
	extract( shortcode_atts(
		array(
			'username' => 'cfarence',
			'limit' => '5'
		), $atts )
	);
	
	// Init the cache system.
	$cache = new Gitlab_Cache();
	// Set custom timeout in seconds.
	$cache->timeout = get_option('wpgitlab_cache_time', 600);
		
	$repositories = $cache->get(username . '.repositories.json');
	if($repositories == null) {
		$gitlab = new gitlab($username);
		$repositories = $gitlab->get_repositories();
		$cache->set($username . '.repositories.json', $repositories);
	}
	
	$repositories = array_slice($repositories, 0, $limit);
	$html = '<ul>';
	foreach($repositories as $repository){
		$html .=  '<li><a href="'. $repository['web_url'] . '" title="'.$repository['description'].'">' . $repository['name'] . '</a></li>';
	}
	$html .= '</ul>';
	return $html;
}
add_shortcode('gitlab-repos', 'glrepos_shortcode');

/*
 * Commits shortcode.
 */
function glcommits_shortcode($atts) {
	extract( shortcode_atts(
		array(
			'username' => 'cfarence',
			'repository' => '',
			'limit' => '5'
		), $atts )
	);
	
	// Init the cache system.
	$cache = new Gitlab_Cache();
	// Set custom timeout in seconds.
	$cache->timeout = get_option('wpgitlab_cache_time', 600);
		
	$commits = $cache->get($username . '.' . $repository . '.commits.json');
	if($commits == null) {
		$gitlab = new gitlab($username, $repository);
		$commits = $gitlab->get_commits();
		$cache->set($username . '.' . $repository . '.commits.json', $commits);
	}

	$commits = array_slice($commits, 0, $limit);
	$html = '<ul>';
	foreach($commits as $commit){
		$html .=  '<li><a href="' . $commit->web_url . '" title="' . $commit->message . '">' . $commit->message . '</a></li>';
	}
	$html .= '</ul>';
	return $html;
}
add_shortcode('gitlab-commits', 'glcommits_shortcode');

/*
 * Issues shortcode.
 */
function glissues_shortcode($atts) {
	extract( shortcode_atts(
		array(
			'username' => 'cfarence',
			'repository' => '',
			'limit' => '5'
		), $atts )
	);
	
	// Init the cache system.
	$cache = new Gitlab_Cache();
	// Set custom timeout in seconds.
	$cache->timeout = get_option('wpgitlab_cache_time', 600);
		
	$issues = $cache->get($username . '.' . $repository . '.issues.json');
	if($issues == null) {
		$gitlab = new gitlab($username, $repository);
		$issues = $gitlab->get_issues();
		$cache->set($username . '.' . $repository . '.issues.json', $issues);
	}
	
	$issues = array_slice($issues, 0, $limit);
	$html = '<ul>';
	foreach($issues as $issue){
		$html .=  '<li><a href="' . $issue->web_url . '" title="' . $issue->title . '">' . $issue->title . '</a></li>';
	}
	$html .= '</ul>';
	return $html;
}
add_shortcode('gitlab-issues', 'glissues_shortcode');

