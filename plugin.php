<?php
/*
Plugin Name: Git Manager
Plugin URI:
Description:
Author: wokamoto
Version: 0.0.1
Author URI: http://dogmap.jp/

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html

  Copyright 2012 (email : wokamoto1973@gmail.com)

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
if ( !class_exists('Git') )
	require_once( dirname(__FILE__).'/includes/Git.php' );

class wp_git_manager {
	private $repo;
	private $repo_path;
	private $remote = 'origin';
	private $branches = array('master');
	
	CONST NONCE_ACTION = 'wp_git_manager-';

	function __construct( $repo_path = null){
		if ( !isset($repo_path) )
			$repo_path = ABSPATH;
		$this->repo_path = $repo_path;
		$this->repo = Git::open($repo_path);

		add_action('admin_bar_menu', array(&$this, 'admin_bar_menu'), 9999);
		add_action('admin_print_scripts', array(&$this, 'admin_scripts'));
		add_action('admin_footer', array(&$this, 'admin_footer'));
		add_action('wp_ajax_git-pull', array(&$this, 'action_callback_git_pull'));
	}
	
	public function admin_bar_menu($bar){
		if ( is_admin() ) {
			$bar->add_menu(array(
				"id" => "git-manager",
				"title" => "Git Manager",
				"href" => false,
			));

			foreach ( $this->branches as $branch ) {
				$nonce = wp_create_nonce(self::NONCE_ACTION.$branch);
				$bar->add_menu(array(
					"parent" => "git-manager",
					"id" => "git-pull-{$branch}",
					"title" => "Pull {$this->remote} {$branch}",
					"href" => admin_url("admin-ajax.php?action=git-pull&remote={$this->remote}&branch={$branch}&nonce={$nonce}"),
					"meta" => false,
				));
			}
		}
	}
	
	public function action_callback_git_pull() {
		$result = false;
		$err_message = '';
		$remote = isset($_GET['remote']) ? $_GET['remote'] : $this->remote;
		$branch = isset($_GET['branch']) ? $_GET['branch'] : $this->branches[0];
		if ( is_user_logged_in() ) {
			$nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
			if ( wp_verify_nonce($nonce, self::NONCE_ACTION.$branch) ) {
				try {
					$result = $this->repo->pull( $remote, $branch );
				} catch (Exception $e) {
					$err_message = $e->getMessage();
				}
			} else {
				$err_message = 'Security check error.';
			}
		} else {
			$err_message = 'No logged in.';
		}
		$json = json_encode(array(
			'remote' => $remote,
			'branch' => $branch,
			'result' => $result,
			'err_message' => $err_message,
		));
		
		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_bloginfo('charset') );
		echo $json;
		die();
	}

	public function admin_scripts(){
		wp_enqueue_script( 'jquery' );
	}

	public function admin_footer(){
?>
<script type="text/javascript">
jQuery(function($){
	function git_pull_success(data){
		if (data.result)
			alert(data.result);
		else
			alert(data.err_message);
	}
<?php foreach ( $this->branches as $branch ) { ?>
	$('#wp-admin-bar-git-pull-<?php echo $branch ?> a').click(function(){
		$.ajax({
			url: this.href,
			success: git_pull_success,
		})
		return false;
	});
<?php } ?>
});
</script>
<?php
	}
}

new wp_git_manager();
