<?php

/****************************************************************
 *
 * Plugin Name: Syndicate Out
 *  Plugin URI: http://www.flutt.co.uk/development/wordpress-plugins/syndicate-out/
 *     Version: 0.8.4
 * Text Domain: syndicate-out
 * Domain Path: /lang
 * Description: Syndicates posts made in any specified category to another 
 *              WP blog using WordPress' built in XML-RPC functionality.
 *              As of version 0.8.4, automatically pushes Author info with post.
 *      Author: ConfuzzledDuck
 *              David Negley
 *  Author URI: http://www.flutt.co.uk/
 *              http://blog.mishkandavid.net
 *
 ***************************************************************/

#
#  syndicate-out.php
#
#  Created by Jonathon Wardman on 09-07-2009.
#  Copyright 2009 - 2014, Jonathon Wardman. All rights reserved.
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  You may obtain a copy of the License at:
#  http://www.gnu.org/licenses/gpl-3.0.txt
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.


// Nothing in this plugin works outside of the admin area, so don't bother
// loading it if we're not looking at the admin panel...
if ( is_admin() ) {

 /* Setup section. */

	 // Global constants and variables relating to posts...
	define( 'SO_OPTIONS_VERSION', 3 );

	 // Register functions...
	add_action( 'plugins_loaded', 'syndicate_out_init' );
	add_action( 'admin_menu', 'syndicate_out_menu' );
	add_action( 'add_meta_boxes_post', 'syndicate_out_meta_box' );
	add_action( 'admin_init', 'syndicate_out_register_settings' );
	add_action( 'save_post', 'syndicate_out_post' );
	add_filter( 'plugin_action_links', 'syndicate_out_settings_link', 10, 2 );

	// Register the plugin activation and delete functions...
	//register_activation_hook( __FILE__, 'syndicate_out_activate' );
	//register_uninstall_hook( __FILE__, 'syndicate_out_delete' );
	
 /* Admin section. */
 
	 // Plugin initialisation...
	function syndicate_out_init() {

		load_plugin_textdomain( 'syndicate-out', false, dirname( plugin_basename( __FILE__ ) ).'/lang/' );
	
	}

	 // Admin menu...
	function syndicate_out_menu() {
	
		add_submenu_page( 'options-general.php', 'Syndicate Out Settings', 'Syndication', 'manage_options', 'syndicate_out', 'syndicate_out_admin' );

	}

	 // Settings link on plugins page...
	function syndicate_out_settings_link( $links, $file ) {

		if ( plugin_basename( __FILE__ ) == $file ) {
			array_push( $links, '<a href="options-general.php?page=syndicate_out">'.__( 'Settings', 'syndicate-out' ).'</a>' );
		}
		return $links;

	}

	 // Register valid admin options...
	function syndicate_out_register_settings() {
	
		register_setting( 'syndicate-out-options', 'so_options', 'syndicate_out_sanitize_options' );

	}

	 // Admin page...
	function syndicate_out_admin() {
	
		if ( false === ( $syndicateOutOptions = get_option( 'so_options' ) ) ) {
			$syndicateOutOptions['group'][] = array( 'category' => null,
			                                         'syndicate_category' => 'none',
			                                         'servers' => array( array( 'server' => '',
			                                                                    'username' => '',
			                                                                    'password' => '' ) ) );
		}
		$newServerRows = get_transient( 'so_new_servers' );
		$newGroupRows = get_transient( 'so_new_groups' );

		require_once( 'so-options.php' );

	}
	
	 // Meta box (only shows when one or more group has 'post' as the trigger...
	function syndicate_out_meta_box( $post ) {

		if ( false !== ( $syndicateOutOptions = get_option( 'so_options' ) ) ) {
			if ( isset( $syndicateOutOptions['group'] ) && is_array( $syndicateOutOptions['group'] ) ) {
				foreach ( $syndicateOutOptions['group'] AS $syndicationGroup) {
					if ( -2 == $syndicationGroup['category'] ) {
						add_meta_box( 'syndicateoutdiv', __( 'Syndicate Post', 'syndicate-out' ), 'syndicate_out_meta_box_content', 'post', 'side', 'default', $syndicateOutOptions );
						break;
					}
				}
			}
		}

	}
	
	 // Meta box content...
	function syndicate_out_meta_box_content( $post, $metabox ) {
	
		if ( false !== ( $syndicateOutOptions = $metabox['args'] ) ) {
			if ( isset( $syndicateOutOptions['group'] ) && is_array( $syndicateOutOptions['group'] ) ) {
				$postSoMeta = get_post_meta( $post->ID, '_so_remote_posts', true );
				if ( ! empty( $postSoMeta ) ) {
					$postSoMeta = unserialize( $postSoMeta );
					$syndicatedGroups = $postSoMeta['group'];
				}
				foreach ( $syndicateOutOptions['group'] AS $syndicationGroupKey => $syndicationGroup) {
					if ( -2 == $syndicationGroup['category'] ) {
						echo '<input type="checkbox" name="so_syndicate[group]['.htmlentities2( $syndicationGroupKey ).']" value="1"'.( ( isset( $syndicatedGroups[$syndicationGroupKey] ) && ( count( $syndicatedGroups[$syndicationGroupKey] ) ) > 0 ) ? ' checked="checked"' : '' ).' /><span style="font-weight: bold;">'.esc_html( sprintf( __( 'Syndication Group %s', 'syndicate-out' ), number_format_i18n( ( $syndicationGroupKey + 1 ) ) ) ).'</span><br />'.PHP_EOL;
						foreach ( $syndicationGroup['servers'] AS $syndicationGroupServerKey => $syndicationGroupServer ) {
							echo '<span style="margin-left: 21px;">'.esc_html( $syndicationGroupServer['server'] ).'</span><br />'.PHP_EOL;
						}
					}
				}
			}
		}

	}

 /* Post / action section. */
 
	 // Sanitize and organise the all settings...
	function syndicate_out_sanitize_options( $options ) {

		if ( ! isset( $options['options_version'] ) ) {
	 // Delete any groups which have been flagged for deletion...
			if ( isset( $options['deletegroup'] ) ) {
				foreach ( $options['deletegroup'] AS $groupKey => $buttonValue ) {
					if ( array_key_exists( $groupKey, $options ) ) {
						unset( $options[$groupKey] );
					}
				}
				unset( $options['deletegroup'] );
			}
		
	 // Save all group settings...
			$addRowsArray = array();
			$newOptions = array( 'group' => array() );
			if ( isset( $options['group'] ) && is_array( $options['group'] ) ) {
				foreach ( $options['group'] AS $groupId => $groupOptions ) {
				
	 // If this group isn't flagged for deletion...
					if ( ! isset( $groupOptions['deletegroup'] ) ) {
				
	 // Flag new rows, if required...
						if ( isset( $groupOptions['addrowbutton'] ) && is_numeric( $groupOptions['addrow'] ) && $groupOptions['addrow'] > 0 ) {
							$addRowsArray[$groupId] = $groupOptions['addrow'];
						}

	 // Triggers and trigger category...
						switch ( $groupOptions['trigger'] ) {
							case 'all':
								$newOptions['group'][$groupId]['category'] = -1;
							break;
							case 'post':
								$newOptions['group'][$groupId]['category'] = -2;
							break;
							case 'category':
								if ( is_numeric( $groupOptions['category'] ) ) {
									$newOptions['group'][$groupId]['category'] = $groupOptions['category'];
									break;
								}
							case 'disable': default:
								$newOptions['group'][$groupId]['category'] = 'none';
							break;
						}

	 // Transmit category...
						switch ( $groupOptions['syndicate_category'] ) {
							case 'all': case 'syndication':
								$newOptions['group'][$groupId]['syndicate_category'] = $groupOptions['syndicate_category'];
							break;
							default:
								$newOptions['group'][$groupId]['syndicate_category'] = 'none';
							break;
						}

	 // Servers...
						foreach ( $groupOptions['servers'] AS $serverKey => $serverDetails ) {
							if ( ! empty( $serverDetails['server'] ) ) {
							
								$remoteServer = trim( $serverDetails['server'] );
								if ( ( 'http://' != substr( $remoteServer, 0, 7 ) ) && ( 'https://' != substr( $remoteServer, 0, 8 ) ) ) {
									$remoteServer = 'http://'.$remoteServer;
								}
								if ( '/' != substr( $remoteServer, -1 ) ) {
									$remoteServer .= '/';
								}
								$newOptions['group'][$groupId]['servers'][$serverKey]['server'] = $remoteServer;
								$newOptions['group'][$groupId]['servers'][$serverKey]['username'] = $serverDetails['username'];
								$newOptions['group'][$groupId]['servers'][$serverKey]['password'] = $serverDetails['password'];

	 // Authentication and API version...
								if ( include_once(  ABSPATH . WPINC . '/class-IXR.php' ) ) {
									if ( include_once(  ABSPATH . WPINC . '/class-wp-http-ixr-client.php' ) ) {
										$xmlrpc = new WP_HTTP_IXR_CLIENT( $serverDetails['server'].'xmlrpc.php' );
										$xmlrpc->query( 'wp.getOptions', array( 0, $serverDetails['username'], $serverDetails['password'], array( 'software_name', 'software_version', 'so_api' ) ) );
										$xmlrpcResponse = $xmlrpc->getResponse();
										if ( '403' == $xmlrpcResponse['faultCode'] ) {
											$newOptions['group'][$groupId]['servers'][$serverKey]['authenticated'] = false;
											$newOptions['group'][$groupId]['servers'][$serverKey]['api'] = __( 'Unknown', 'syndicate-out' );
										} else {
											$newOptions['group'][$groupId]['servers'][$serverKey]['authenticated'] = true;
											if ( isset( $xmlrpcResponse['so_api'] ) ) {
												$newOptions['group'][$groupId]['servers'][$serverKey]['api'] = sprintf( __( 'Syndicate Out API v%s', 'syndicate-out' ), $xmlrpcResponse['so_api']['value'] );
											} else {
												$newOptions['group'][$groupId]['servers'][$serverKey]['api'] = $xmlrpcResponse['software_name']['value'].' '.$xmlrpcResponse['software_version']['value'];
											}
										}
									}
								}

							}
						}
					
					}
					
				}
			}
			
	 // Set the transient relating to new server rows...
			if ( count( $addRowsArray ) > 0 ) {
				set_transient( 'so_new_servers', $addRowsArray, 5 );
			}
			
	 // Set the transient relating to new groups...
			if ( isset( $options['addgroupbutton'] ) && is_numeric( $options['addgroup'] ) && $options['addgroup'] > 0 ) {
				set_transient( 'so_new_groups', $options['addgroup'], 5 );
			}

	 // Grab the old settings...
			$oldOptions = get_option( 'so_options' );
			if ( isset( $oldOptions['options_version'] ) ) {
				$newOptions['options_version'] = $oldOptions['options_version'];
			} else {
				$newOptions['options_version'] = SO_OPTIONS_VERSION;
			}
			
			return $newOptions;
		} else {
			return $options;
		}

	}

	 // Carry out the syndication on post insert...
	function syndicate_out_post( $postId ) {
	
		if ( wp_is_post_revision( $postId ) && ! wp_is_post_autosave( $postId ) ) {
	
			if ( $soOptions = get_option( 'so_options' ) ) {
				if ( isset( $soOptions['group'] ) && is_array( $soOptions['group'] ) ) {

					$activeGroups = array();

	 // Groups activated by global settings...
					foreach ( $soOptions['group'] AS $syndicationGroupKey => $syndicationGroup ) {
						$categories = get_the_category( $postId );
						if ( 0 == count( $categories ) ) {
							if ( null != $_POST['post_category'] ) {
								$categories = $_POST['post_category'];
							}
						}
						if ( ( -1 == $syndicationGroup['category'] ) || in_array( $syndicationGroup['category'], $categories ) ) {
							$activeGroups[$syndicationGroupKey] = $syndicationGroup;
						}
					}
				
	 // Groups activated by per-post selection...
					if ( isset( $_POST['so_syndicate']['group'] ) && is_array( $_POST['so_syndicate']['group'] ) ) {
						foreach ( $_POST['so_syndicate']['group'] AS $groupKey => $value ) {
							if ( ( '1' == $value ) && is_int( $groupKey ) ) {
								if ( ! array_key_exists( $groupKey, $activeGroups ) ) {
									$activeGroups[$groupKey] = $soOptions['group'][$groupKey];
								}
							}
						}
					}

					if ( count( $activeGroups ) > 0 ) {
		
	 // Get required post information...
						$postData = get_post( $postId );

						if ( in_array( $postData->post_status, array( 'publish', 'inherit', 'future' ) ) ) {
							
	 // Include the required IXR libraries...
							if ( include_once(  ABSPATH . WPINC . '/class-IXR.php' ) ) {
								if ( include_once(  ABSPATH . WPINC . '/class-wp-http-ixr-client.php' ) ) {

									if ( 'inherit' == $postData->post_status ) {
										$postMetaId = $postData->post_parent;
										$postData->post_status = get_post_status( $postMetaId );
									} else {
										$postMetaId = $postId;
									}

	 // General post related stuff...
	 // 2014-06-22 DHN Added post_author to support synchronization of authors across network.
									$syndicateElements = array( 'post_type', 'post_status', 'post_title',
									                            'post_excerpt', 'post_content', 'post_format',
									                            'post_password', 'comment_status', 'ping_status',
									                            'post_author',
									                            'post_date_gmt' );
									$remotePost = array();
									foreach ( $postData AS $dataItemKey => $dataItemContent ) {
										if ( in_array( $dataItemKey, $syndicateElements ) ) {
											$remotePost[$dataItemKey] = $dataItemContent;
										}
									}
									if ( isset( $remotePost['post_date_gmt'] ) ) {
										$remotePost['post_date_gmt'] = new IXR_Date( strtotime( $remotePost['post_date_gmt'] ) );
									}

	 // Custom fields...
									$postMeta = has_meta( $postMetaId );
									if ( is_array( $postMeta ) ) {
										$remotePost['custom_fields'] = array();
										foreach ( $postMeta AS $metaSingle ) {
											if ( $metaSingle['meta_key'][0] != '_' ) {
												$remotePost['custom_fields'][] = array( 'key' => $metaSingle['meta_key'],
												                                        'value' => $metaSingle['meta_value'] );
											}
										}
									}
						
	 // Tags...
									$remotePost['terms_names'] = array();
									if ( $postTags = syndicate_out_get_tags( $postId ) ) {
										$remotePost['terms_names']['post_tag'] = array();
										foreach ( $postTags AS $postTag ) {
											$remotePost['terms_names']['post_tag'][] = $postTag->name;
										}
									}

	 // Categories...
									$groupCategoryArray = array();
									foreach ( $activeGroups AS $groupKey => $groupDetails ) {
										if ( 'none' != $groupDetails['syndicate_category'] ) {
											if ( 'syndication' == $groupDetails['syndicate_category'] && ( -1 != $syndicationGroup['category'] ) ) {
												if ( $groupDetails['category'] > 0 ) {
													$groupCategoryArray[$groupKey] = array( get_cat_name( $groupDetails['category'] ) );
												}
											} else if ( ( 'all' == $groupDetails['syndicate_category'] ) || ( -1 == $syndicationGroup['category'] ) ) {
												$categories = $_POST['post_category'];
												$groupCategoryArray[$groupKey] = array();
												foreach ( $categories AS $postCategory ) {
													if ( 0 != $postCategory ) {
														$groupCategoryArray[$groupKey][] = get_cat_name( $postCategory );
													}
												}
											}
										}

									}

	 // Publish the post to the remote blog(s)...
									if ( false !== ( $remotePostIds = unserialize( get_post_meta( $postMetaId, '_so_remote_posts', true ) ) ) ) {
										if ( ! isset( $remotePostIds['options_version'] ) ) {
											$newRemotePostIds = array( 'options_version' => SO_OPTIONS_VERSION );
											foreach ( $remotePostIds AS $serverKey => $remotePostId ) {
												$newRemotePostIds['group'][0][$serverKey] = $remotePostId;
											}
											$remotePostIds = $newRemotePostIds;
											update_post_meta( $postMetaId, '_so_remote_posts', serialize( $remotePostIds ) );
										}
										foreach ( $remotePostIds['group'] AS $groupKey => $remoteServers ) {
											$compiledGroupPost = $remotePost;
											if ( isset( $groupCategoryArray[$groupKey] ) ) {
												$compiledGroupPost['terms_names']['category'] = $groupCategoryArray[$groupKey];
											}
											foreach ( $remoteServers AS $serverKey => $remotePostId ) {
												if ( is_numeric( $remotePostId ) ) {
													if ( isset( $soOptions['group'][$groupKey]['servers'][$serverKey] ) ) {
														$thisServerPost = syndicate_out_clean_for_remote( $soOptions['group'][$groupKey]['servers'][$serverKey]['server'], $soOptions['group'][$groupKey]['servers'][$serverKey]['username'], $soOptions['group'][$groupKey]['servers'][$serverKey]['password'], $compiledGroupPost );
														$xmlrpc = new WP_HTTP_IXR_CLIENT( $soOptions['group'][$groupKey]['servers'][$serverKey]['server'].'xmlrpc.php' );
														$xmlrpc->query( 'wp.editPost', array( 0, $soOptions['group'][$groupKey]['servers'][$serverKey]['username'], $soOptions['group'][$groupKey]['servers'][$serverKey]['password'], $remotePostId, $thisServerPost ) );
													}
												}
											}
										}
									} else {
										$remotePostInformation = array( 'options_version' => SO_OPTIONS_VERSION );
										foreach ( $activeGroups AS $groupKey => $activeGroup ) {
											$compiledGroupPost = $remotePost;
											if ( isset( $groupCategoryArray[$groupKey] ) ) {
												$compiledGroupPost['terms_names']['category'] = $groupCategoryArray[$groupKey];
											}
											foreach ( $activeGroup['servers'] AS $serverKey => $serverDetails ) {
												$thisServerPost = syndicate_out_clean_for_remote( $soOptions['group'][$groupKey]['servers'][$serverKey]['server'], $soOptions['group'][$groupKey]['servers'][$serverKey]['username'], $soOptions['group'][$groupKey]['servers'][$serverKey]['password'], $compiledGroupPost );
												$xmlrpc = new WP_HTTP_IXR_CLIENT( $serverDetails['server'].'xmlrpc.php' );
												$xmlrpc->query( 'wp.newPost', array( 0, $serverDetails['username'], $serverDetails['password'], $thisServerPost ) );
												$remotePostInformation['group'][$groupKey][$serverKey] = $xmlrpc->getResponse();
											}
										}
										update_post_meta( $postMetaId, '_so_remote_posts', serialize( $remotePostInformation ) );
									}

								}
							}

						}

					}

				}
			}
		
		}

	}
	
	 // Check the post is valid for (will be accepted by) the remote server
	 // specified, and if not strip out anything which might cause problems...
	function syndicate_out_clean_for_remote( $remoteAddress, $remoteUsername, $remotePassword, $compiledGroupPost ) {

		// Update Author record on remote site.

		// Get local author information
		$localAuthor = get_user_by( 'id', $compiledGroupPost['post_author'] );

		// Lookup local author on remote server
		$remoteServer = new WP_HTTP_IXR_CLIENT( $remoteAddress . 'xmlrpc.php' );
		$remoteAuthors = $remoteServer->query( 'wp.getAuthors', array( 0, $remoteUsername, $remotePassword ) );

		if ( count( $remoteAuthors ) > 0 )
		{
			foreach ( $remoteAuthors AS $author => $authorDetails )
			{
				if ($localAuthor['user_email'] == $authorDetails['user_email'] )
				{
					$remoteAuthor = $authorDetails;
					break;
				}
			}

			// If we found a matching user email address on the remote server,
			// then we want to update his information over there from the Host.
			if ( isset( $remoteAuthor ) )
			{
				foreach ( $remoteAuthor AS $fieldKey => $fieldValue )
				{
					if ( !in_array( $fieldKey, array('id', 'password', 'email address' ) )
					{
						$remoteAuthor[$fieldKey] = $localAuthor[$fieldKey];
					}
				}
			}
			else
			{
				// We did not find matching email on remote server.
				// Send local author information to remote server for new record.
				$remoteServer = new WP_HTTP_IXR_CLIENT( $remoteAddress . 'xmlrpc.php' );
				$newRemoteAuthor = array();
				$newRemoteAuthor['username'] = localAuthor['username'];
				$newRemoteAuthor['password'] = localAuthor['password'];
				$newRemoteAuthor['content'] = array();
				$newRemoteAuthor['content']['first_name']   = localAuthor['first_name'];
				$newRemoteAuthor['content']['last_name']    = localAuthor['last_name'];
				$newRemoteAuthor['content']['url']          = localAuthor['url'];
				$newRemoteAuthor['content']['display_name'] = localAuthor['display_name'];
				$newRemoteAuthor['content']['nickname']     = localAuthor['nickname'];
				$newRemoteAuthor['content']['nicename']     = localAuthor['nicename'];
				$newRemoteAuthor['content']['bio']          = localAuthor['bio'];

				$remoteServer = new WP_HTTP_IXR_CLIENT( $remoteAddress . 'xmlrpc.php' );
				$remoteServer->query( 'wp.getAuthors', array( 0, $remoteUsername, $remotePassword ) );
			}
		}


		// Set post type to "Post"
		if ( ( 'revision' == $compiledGroupPost['post_type'] ) ) {
			$compiledGroupPost['post_type'] = 'post';
		}

		return $compiledGroupPost;
	
	}

	function xlate_author($recPost)
	{

		
		// Based on return code, either 
		// update or create new author on remote server
		
		// Set id of remote author id into post data
		
	}
	function get_remote_user_id( $user_mail )
	{
	 // Author Data fields...
						$postAuthor = get_user_by( 'id', $postData->post_author );
									if ( is_array( $postAuthor ) ) {
										$remotePost['author_fields'] = array();
										foreach ( $postAuthor AS $authorField ) {
											$remotePost['author_fields'][] = array( 'key' => $authorField['author_key'],
												                                        'value' => $authorField['author_value'] );
											}
										}

	}

	 // Get a list of tags for this post...
	function syndicate_out_get_tags( $postId ) {

		$terms = get_object_term_cache( $postId, 'post_tag' );
		if ( false === $terms ) {
			$terms = wp_get_object_terms( $postId, 'post_tag' );
		}

		if ( empty( $terms ) ) {
			return false;
		}

		return $terms;

	}

 /* Maintenance section. */

	 // Activation function. Unused as of version 0.8...
	function syndicate_out_activate() {

		// Currently not used.

	}

	 // Updates the any old versions of settings to the latest version...
	function syndicate_out_update_settings( $currentSettings ) {

		$newSettings = $currentSettings;
		switch ( $currentSettings['options_version'] ) {
			case 0: case 1: # Upgrades version 0 or 1 to version 2
				unset( $newSettings['options_version'] );
				$newSettings['group'][0] = $newSettings;
			case 2: # Upgrades from version 2 to version 3; adds authenticated and api
				if ( isset( $newSettings['group'] ) && is_array( $newSettings['group'] ) ) {
					foreach ( $newSettings['group'] AS $groupId => $groupArray ) {
						if ( isset( $groupArray['servers'] ) && is_array( $groupArray['servers'] ) ) {
							foreach ( $groupArray['servers'] AS $serverId => $serverDetails ) {
								if ( ! isset( $serverDetails['authenticated'] ) ) {
									$newSettings['group'][$groupId]['servers'][$serverId]['authenticated'] = null;
								}
								if ( ! isset( $serverDetails['api'] ) ) {
									$newSettings['group'][$groupId]['servers'][$serverId]['api'] = null;
								}
							}
						}
					}
				}
			break;
		}
		$newSettings['options_version'] = SO_OPTIONS_VERSION;
		update_option( 'so_options', $newSettings );

	}

	 // Check the plugin options version and update if required...
	if ( $currentSettings = get_option( 'so_options' ) ) {
		if ( isset( $currentSettings['options_version'] ) && ( $currentSettings['options_version'] < SO_OPTIONS_VERSION ) ) {
			syndicate_out_update_settings( $currentSettings );
		}
	}

}
