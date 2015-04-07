<?php
/**
 * Github
 * Author: Pablo Cornehl
 * Author URI: http://www.seinoxygen.com
 * Version: 1.1
 */
class Gitlab {
	private $api_url = null;
	private $api_key = null;
	private $username = null;
	private $repository = null;
	
	public function Gitlab($username = 'seinoxygen', $repository = 'wp-github') {
		$this->username = $username;
		$this->repository = $repository;
		
		/**
		 * Increase execution time.
		 * 
		 * Sometimes long queries like fetch all issues from all repositories can kill php.
		 */
		set_time_limit(90);
	}
	
	/**
	 * Get response content from url.
	 * 
	 * @param	$path String
	 */
	public function get_response($path){
		$this->api_url = get_option('wpgitlab_url', null).'/api/v3/';
		$this->api_key = get_option('wpgitlab_api_key', null);
		$ch = curl_init();
		$headers = array();
		$headers[] = 'PRIVATE-TOKEN: '.$this->api_key;
		curl_setopt($ch, CURLOPT_URL, $this->api_url . $path);
		curl_setopt($ch, CURLOPT_USERAGENT, 'wp-github');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
	
	/**
	 * Get the id of the user specified
	 */
	public function get_user_id($username) {
		$contents = $this->get_response('users?search='.$username);
		if($contents == true) {
			$contents = json_decode($contents,true);
			return $contents[0]['id'];
		}
	}
	
	/**
	 * Git the id from project
	 */
	public function get_project($username, $project) {
		$namespace = urlencode($username.'/'.$project);
		$contents = $this->get_response('projects/'.$namespace);
		$project = json_decode($contents,true);
		return $project;
	}
	
	/**
	 * Return user profile.
	 */
	public function get_profile(){
		$id = $this->get_user_id($this->username);
		$number_repo = count($this->get_repositories());
		$contents = $this->get_response('users/' . $id);
		$contents = json_decode($contents);
		$contents->number_repo = $number_repo;
		if($contents == true) {
		 	return $contents;
		}
		return null;
	}
	
	/**
	 * Return user events.
	 */
	public function get_events(){
		$contents = $this->get_response('users/' . $this->username . '/events');
		if($contents == true) {
		 	return json_decode($contents);
		}
		return null;
	}
	
	/**
	 * Return user repositories.
	 */
	public function get_repositories(){
		$contents = $this->get_response('/projects/owned');
		if($contents == true) {
			//Strip out the private projects
			$return = [];
			$contents = json_decode($contents);
			foreach($contents as $value) {
				if($value->visibility_level == 20) {
					$return[] = $value;
				}
			}
		 	return $return;
		}
		return null;
	}
	
	/**
	 * Return repository commits. If none is provided will fetch all commits from all public repositories from user.
	 */
	public function get_commits(){
		$data = array();
		$gitlab_url = get_option('wpgitlab_url', null);
		if(!empty($this->repository)){
			$project = $this->get_project($this->username, $this->repository);
			$contents = $this->get_response('projects/' . $project['id'] . '/repository/commits');
			$contents = json_decode($contents);
			$return = [];
			foreach($contents as $value) {
				$value->web_url = $gitlab_url.'/'.$this->username.'/'.$this->repository.'/commit/'.$value->id;
				$return[] = $value;
			}
			if($contents == true) {
				$data = array_merge($data, $return);
			}
		}
		else{
			// Fetch all public repositories
			$repos = $this->get_repositories();
			if($repos == true) {
				// Loop through public repos and get all commits
				foreach($repos as $repo){
					$contents = $this->get_response('projects/' . $repo->id . '/repository/commits');
					$contents = json_decode($contents);
					$return = [];
					foreach($contents as $value) {
						$value->web_url = $gitlab_url.'/'.$this->username.'/'.$repo->path.'/commit/'.$value->id;
						$return[] = $value;
					}
					if($contents == true) {
						$data = array_merge($data, $return);
					}
				}
			}
		}

		return $data;
	}
	
	/**
	 * Return repository issues. If none is provided will fetch all issues from all public repositories from user.
	 */
	public function get_issues(){
		$data = array();
		$gitlab_url = get_option('wpgitlab_url', null);
		if(!empty($this->repository)){
			$project = $this->get_project($this->username, $this->repository);
			$contents = $this->get_response('projects/'.$project['id'].'/issues?state=opened');
			$return = [];
			$contents = json_decode($contents);
			foreach($contents as $value) {
				$value->web_url = $gitlab_url.'/'.$this->username.'/'.$this->repository.'/issues/'.$value->iid;
				$return[] = $value;
			}
			if($return == true) {
				$data = $return;
			}
		}
		else{
			// Fetch all public repositories
			$repos = $this->get_repositories();
			if($repos == true) {
				// Loop through public repos and get all issues
				foreach($repos as $repo){
					$contents = $this->get_response('projects/'.$repo->id.'/issues?state=opened');
					$contents = json_decode($contents);
					$issue = [];
					foreach($contents as $value) {
						$value->web_url = $gitlab_url.'/'.$this->username.'/'.$repo->path.'/issues/'.$value->iid;
						$issue[] = $value;
					}
					if($contents == true) {
						$data = array_merge($data, $issue);
					}
				}
			}
		}
		
		// Sort response array
		usort($data, array($this, 'order_issues'));
		
		return $data;
	}
	
	public function get_gists(){
		$contents = $this->get_response('users/' . $this->username . '/gists');
		if($contents == true) {
		 	return json_decode($contents);
		}
		return null;
	}
	
	/**
	 * Get username.
	 */
	public function get_username() {
		return $this->username;
	}
	
	/**
	 * Get repository.
	 */
	public function get_repository() {
		return $this->repository;
	}
		
	/**
	 * Sort commits from newer to older.
	 */
	public function order_commits($a, $b){
		$a = strtotime($a->commit->author->date);
		$b = strtotime($b->commit->author->date);
		if ($a == $b){
			return 0;
		}
		else if ($a > $b){
			return -1;
		}
		else {            
			return 1;
		}
	}
	
	/**
	 * Sort issues from newer to older.
	 */
	public function order_issues($a, $b){
		$a = strtotime($a->created_at);
		$b = strtotime($b->created_at);
		if ($a == $b){
			return 0;
		}
		else if ($a > $b){
			return -1;
		}
		else {            
			return 1;
		}
	}
}
?>