<?php
/**
*
* @package phpBB Extension - Reassign First Post
* @copyright (c) 2016 Sheer
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace sheer\reassignfirstpost\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
/**
* Assign functions defined in this class to event listeners in the core
*
* @return array
* @static
* @access public
*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'							=> 'load_language_on_setup',
			'core.posting_modify_submit_post_after'		=> 'reasign_first_post',
			'core.posting_modify_template_vars'			=> 'posting_modify_template_vars',
		);
	}

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/**
	* Constructor
	*/
	public function __construct(
		\phpbb\template\template $template,
		\phpbb\auth\auth $auth,
		\phpbb\db\driver\driver_interface $db
	)
	{
		$this->template = $template;
		$this->auth = $auth;
		$this->db = $db;
	}

	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'sheer/reassignfirstpost',
			'lang_set' => 'reassign_first_post',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function posting_modify_template_vars($event)
	{
		$post_data= $event['post_data'];

		if(isset($post_data['post_id']))
		{
			$this->template->assign_vars(array(
				'S_REASIGN_FIRST_POST'			=> ($post_data['topic_first_post_id'] != $post_data['post_id'] && $this->auth->acl_get('m_edit',  $post_data['forum_id'])) ? true : false,
				'S_REASSIGN_FIRST_POST_CHECKED'	=> (isset($_POST['reassign_first_post'])) ? ' checked="checked"' : '',
				)
			);
		}
	}

	public function reasign_first_post($event)
	{
		$mode = $event['mode'];
		$reasign = (isset($_POST['reassign_first_post'])) ? true : false;
		$post_data= $event['post_data'];

		if ($mode == 'edit' && $reasign && $this->auth->acl_get('m_edit', $post_data['forum_id']))
		{
			$post_time = $post_data['post_time'];
			$post_data['post_time'] = $post_data['topic_time'] - 1;
			$post_data['topic_poster'] = $post_data['poster_id'];
			$post_data['topic_first_poster_name'] = $post_data['username'];
			$post_data['topic_first_post_id'] = $post_data['post_id'];

			$sql = 'SELECT user_colour
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' .  $post_data['poster_id'];
			$result = $this->db->sql_query_limit($sql, 1);
			$user_colour = $this->db->sql_fetchfield('user_colour');
			$this->db->sql_freeresult($result);

			$sql = 'UPDATE ' . POSTS_TABLE . ' SET post_time = ' . ($post_data['topic_time'] - 1) . '
				WHERE post_id = ' . $post_data['post_id'];
			$this->db->sql_query($sql);

			$topic_data = array(
				'topic_first_post_id'		=> $post_data['post_id'],
				'topic_time'				=> $post_data['topic_time'] - 1,
				'topic_poster'				=> $post_data['poster_id'],
				'topic_first_poster_name'	=> $post_data['username'],
				'topic_first_poster_colour'	=> $user_colour,
				'topic_title'				=> $post_data['post_subject'],
			);

			$sql = 'UPDATE ' . TOPICS_TABLE . '
				SET ' . $this->db->sql_build_array('UPDATE', $topic_data) . '
				WHERE topic_id = ' . $post_data['topic_id'] . '';
			$this->db->sql_query($sql);
			unset($topic_data);

			$sql = 'SELECT post_id, post_time, poster_id, post_username, post_subject
				FROM ' . POSTS_TABLE . '
				WHERE post_time = (SELECT MAX(post_time) AS max FROM ' . POSTS_TABLE . ' WHERE topic_id = ' . $post_data['topic_id'] . ' )
				AND topic_id = ' . $post_data['topic_id'];
			$result = $this->db->sql_query_limit($sql, 1);

			$row = $this->db->sql_fetchrow($result);

			$last_post_id = $row['post_id'];
			$last_post_time = $row['post_time'];
			$last_poster_id = $row['poster_id'];

			$last_post_subject = $row['post_subject'];
			$this->db->sql_freeresult($result);
			unset($row);

			$sql = 'SELECT username, user_colour
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . $last_poster_id;
			$result = $this->db->sql_query_limit($sql, 1);
			$row = $this->db->sql_fetchrow($result);
			$last_post_username = $row['username'];
			$last_poster_colour = $row['user_colour'];
			$this->db->sql_freeresult($result);

			$topic_data = array(
				'topic_last_post_id'		=> $last_post_id,
				'topic_last_post_time'		=> $last_post_time,
				'topic_last_poster_id'		=> $last_poster_id,
				'topic_last_poster_name'	=> $last_post_username,
				'topic_last_poster_colour'	=> $last_poster_colour,
				'topic_last_post_subject' 	=> $last_post_subject,
			);

			$sql = 'UPDATE ' . TOPICS_TABLE . '
				SET ' . $this->db->sql_build_array('UPDATE', $topic_data) . '
				WHERE topic_id = ' . $post_data['topic_id'] . '';
			$this->db->sql_query($sql);

			$sql = 'SELECT MAX(forum_last_post_time) AS max
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id = ' . $post_data['forum_id'];
			$result = $this->db->sql_query_limit($sql, 1);
			$last_time = (int) $this->db->sql_fetchfield('max');
			$this->db->sql_freeresult($result);

			if ($post_time == $last_time)
			{
				$forum_data = array(
					'forum_last_post_id'		=> $last_post_id,
					'forum_last_poster_id' 		=> $last_poster_id,
					'forum_last_post_subject'	=> $last_post_subject,
					'forum_last_post_time'		=> $last_post_time,
					'forum_last_poster_name'	=> $last_post_username,
					'forum_last_poster_colour'	=> $last_poster_colour,
				);

				$sql = 'UPDATE ' . FORUMS_TABLE . '
						SET ' . $this->db->sql_build_array('UPDATE', $forum_data) . '
						WHERE forum_id = ' . $post_data['forum_id'];
				$this->db->sql_query($sql);
			}
		}
	}
}
