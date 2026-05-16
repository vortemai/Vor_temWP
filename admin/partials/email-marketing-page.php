<?php
/**
 * Email Marketing Page Template
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap vortem-email-marketing-wrap">
	<div id="vortem-email-marketing-app">
		<main class="tab-panels">
			<!-- Emails Panel -->
			<section id="panel-emails" class="panel active" aria-labelledby="Emails">
				<div class="modern-header">
					<div class="modern-header-main">
						<div class="icon-pill">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</div>
						<div>
							<h1 class="title"><?php echo esc_html__( 'Email Marketing', 'vortem-ai' ); ?></h1>
							<p class="subtitle"><?php echo esc_html__( 'Manage, send, and track your emails', 'vortem-ai' ); ?></p>
						</div>
					</div>
					<div class="modern-header-actions">
						<nav class="tabs" role="tablist">
							<button class="tab active" data-tab="emails" role="tab"><?php echo esc_html__( 'Emails', 'vortem-ai' ); ?></button>
							<button class="tab" data-tab="lists" role="tab"><?php echo esc_html__( 'Lists', 'vortem-ai' ); ?></button>
						</nav>
						<button class="btn btn-primary" id="open-create-modal">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								<line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
							<?php echo esc_html__( 'Create Email', 'vortem-ai' ); ?>
						</button>
					</div>
				</div>

				<div class="top-controls">
					<div class="search-wrap">
						<svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="m21 21-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<input id="search-emails" class="input search-input" type="search" placeholder="<?php echo esc_attr__( 'Search emails...', 'vortem-ai' ); ?>" />
					</div>
					<div class="right-controls">
						<div class="view-toggle" role="group" aria-label="<?php echo esc_attr__( 'View toggle', 'vortem-ai' ); ?>">
							<button id="view-cards" class="btn btn-small" aria-pressed="false"><?php echo esc_html__( 'Cards', 'vortem-ai' ); ?></button>
							<button id="view-table" class="btn btn-small" aria-pressed="true"><?php echo esc_html__( 'Table', 'vortem-ai' ); ?></button>
						</div>
						<button id="refresh-emails" class="btn btn-ghost">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<polyline points="23 4 23 10 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>


				<div id="emails-stats" class="stats"></div>


				<div id="emails-cards" class="cards-grid" style="display:none;"></div>

				<div id="pagination-container"></div>

				<div class="table-wrap" id="emails-table-wrap">
					<table class="table" id="emails-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Recipient', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Subject', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Created', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'vortem-ai' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</section>

			<!-- Lists Panel -->
			<section id="panel-lists" class="panel" aria-labelledby="Email Lists">
				<div class="modern-header">
					<div class="modern-header-main">
						<div class="icon-pill">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M23 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</div>
						<div>
							<h1 class="title"><?php echo esc_html__( 'Email Lists', 'vortem-ai' ); ?></h1>
							<p class="subtitle"><?php echo esc_html__( 'Manage, send, and track your email lists', 'vortem-ai' ); ?></p>
						</div>
					</div>
					<div class="modern-header-actions">
						<nav class="tabs" role="tablist">
							<button class="tab active" data-tab="emails" role="tab"><?php echo esc_html__( 'Emails', 'vortem-ai' ); ?></button>
							<button class="tab" data-tab="lists" role="tab"><?php echo esc_html__( 'Lists', 'vortem-ai' ); ?></button>
						</nav>
						<button class="btn btn-primary" id="open-list-modal">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								<line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
							<?php echo esc_html__( 'Create Email List', 'vortem-ai' ); ?>
						</button>
					</div>
				</div>

				<div class="top-controls">
					<div class="search-wrap">
						<svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="m21 21-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<input id="search-lists" class="input search-input" type="search" placeholder="<?php echo esc_attr__( 'Search lists...', 'vortem-ai' ); ?>" />
					</div>
					<div class="right-controls">
						<div class="view-toggle" role="group" aria-label="<?php echo esc_attr__( 'View toggle', 'vortem-ai' ); ?>">
							<button id="view-lists-cards" class="btn btn-small" aria-pressed="false"><?php echo esc_html__( 'Cards', 'vortem-ai' ); ?></button>
							<button id="view-lists-table" class="btn btn-small" aria-pressed="true"><?php echo esc_html__( 'Table', 'vortem-ai' ); ?></button>
						</div>
						<button id="toggle-select-lists" class="btn btn-ghost"><?php echo esc_html__( 'Select', 'vortem-ai' ); ?></button>
						<button id="bulk-delete-lists-quick" class="btn btn-ghost btn-danger" style="display:none;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php echo esc_html__( 'Delete', 'vortem-ai' ); ?>
						</button>
						<button id="refresh-lists" class="btn btn-ghost">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<polyline points="23 4 23 10 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					</div>
				</div>

				<div id="bulk-bar-lists" class="bulk-bar" style="display:none;"></div>

				<div id="lists-stats" class="stats"></div>


				<div id="lists-cards" class="cards-grid" style="display:none;"></div>

				<div id="lists-pagination-container"></div>

				<div class="table-wrap" id="lists-table-wrap">
					<table class="table" id="lists-table">
						<thead>
							<tr>
								<th style="width:36px;"><input type="checkbox" id="select-all-lists" /></th>
								<th><?php echo esc_html__( 'Subject', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Recipients', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Created', 'vortem-ai' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'vortem-ai' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</section>
		</main>

		<div id="toast" class="toast" role="status" aria-live="polite"></div>

		<!-- Toast Alert Modal -->
		<div id="toast-alert" class="toast-alert" role="alert" aria-live="assertive">
			<div class="toast-alert-content">
				<div class="toast-alert-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<div class="toast-alert-message" id="toast-alert-message"></div>
			</div>
		</div>

		<!-- Custom Alert/Confirm Modal -->
		<div class="modal" id="alert-modal" aria-hidden="true" role="dialog" aria-labelledby="alert-modal-title">
			<div class="modal-backdrop" data-close="alert-modal"></div>
			<div class="modal-dialog alert-dialog">
				<div class="alert-header">
					<div class="alert-icon" id="alert-icon"></div>
					<h3 id="alert-modal-title"><?php echo esc_html__( 'Confirm Action', 'vortem-ai' ); ?></h3>
					<button class="btn btn-icon" id="close-alert-modal" aria-label="<?php echo esc_attr__( 'Close', 'vortem-ai' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
				<div class="alert-body">
					<p id="alert-message"><?php echo esc_html__( 'Are you sure you want to proceed?', 'vortem-ai' ); ?></p>
				</div>
				<div class="alert-actions">
					<button class="btn" id="alert-cancel"><?php echo esc_html__( 'Cancel', 'vortem-ai' ); ?></button>
					<button class="btn btn-primary" id="alert-confirm"><?php echo esc_html__( 'Confirm', 'vortem-ai' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Create/Update Email List Modal -->
		<div class="modal" id="list-modal" aria-hidden="true" role="dialog" aria-labelledby="list-modal-title">
			<div class="modal-backdrop" data-close="list-modal"></div>
			<div class="modal-dialog">
				<div class="modal-header">
					<h3 id="list-modal-title"><?php echo esc_html__( 'Create Email List', 'vortem-ai' ); ?></h3>
					<button class="btn btn-icon" id="close-list-modal" aria-label="<?php echo esc_attr__( 'Close', 'vortem-ai' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
				<form id="email-list-form" class="form">
					<input type="hidden" id="email_list_id" />
					<div class="form-row">
						<label for="list_subject"><?php echo esc_html__( 'List Subject', 'vortem-ai' ); ?> *</label>
						<input id="list_subject" class="input" type="text" placeholder="<?php echo esc_attr__( 'Subject for all recipients', 'vortem-ai' ); ?>" required />
					</div>
					<div class="form-row">
						<label for="list_recipients_input"><?php echo esc_html__( 'Recipients', 'vortem-ai' ); ?> *</label>
						<div class="email-tags-container">
							<div id="email-tags-wrapper" class="email-tags-wrapper"></div>
							<div class="email-tags-input-wrapper">
								<input 
									type="email" 
									id="list_recipients_input" 
									class="input email-tags-input" 
									placeholder="<?php echo esc_attr__( 'Enter email address and press Enter or click Add', 'vortem-ai' ); ?>"
									autocomplete="off"
								/>
								<button type="button" id="add-email-btn" class="btn btn-small email-tags-add-btn"><?php echo esc_html__( 'Add', 'vortem-ai' ); ?></button>
							</div>
						</div>
						<input type="hidden" id="list_recipients" name="list_recipients" required />
						<div class="hint"><?php echo esc_html__( 'Enter email addresses one at a time. Click the X on a tag to remove it.', 'vortem-ai' ); ?></div>
					</div>
					<div class="form-row">
						<label for="list_content"><?php echo esc_html__( 'Email Body (optional HTML)', 'vortem-ai' ); ?></label>
						<?php
						$vortem_list_content         = '';
						$vortem_list_editor_id       = 'list_content';
						$vortem_list_editor_settings = array(
							'textarea_name' => 'list_content',
							'textarea_rows' => 12,
							'media_buttons' => true,
							'quicktags'     => true,
							'tinymce'       => array(
								'toolbar1'    => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,spellchecker,fullscreen,wp_adv',
								'toolbar2'    => 'formatselect,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
								'content_css' => false,
								'setup'       => 'function(ed) { ed.on("change", function() { ed.save(); }); }',
							),
							'wpautop'       => false,
						);
						wp_editor( $vortem_list_content, $vortem_list_editor_id, $vortem_list_editor_settings );
						?>
						<div class="hint"><?php echo esc_html__( 'You can use the visual editor or switch to text mode to paste HTML.', 'vortem-ai' ); ?></div>
					</div>
					<div class="form-actions">
						<button type="submit" class="btn btn-primary" id="save-list"><?php echo esc_html__( 'Send', 'vortem-ai' ); ?></button>
						<button type="button" id="preview-list-email" class="btn"><?php echo esc_html__( 'Preview', 'vortem-ai' ); ?></button>
						<button type="button" class="btn" id="reset-list"><?php echo esc_html__( 'Clear', 'vortem-ai' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<!-- Create Email Modal -->
		<div class="modal" id="create-modal" aria-hidden="true" role="dialog" aria-labelledby="create-modal-title">
			<div class="modal-backdrop" data-close="create-modal"></div>
			<div class="modal-dialog">
				<div class="modal-header">
					<h3 id="create-modal-title"><?php echo esc_html__( 'Create New Email', 'vortem-ai' ); ?></h3>
					<button class="btn btn-icon" id="close-create-modal" aria-label="<?php echo esc_attr__( 'Close', 'vortem-ai' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
				<form id="create-email-form" class="form">
					<div class="form-row">
						<label for="recipient"><?php echo esc_html__( 'Recipient', 'vortem-ai' ); ?> *</label>
						<input id="recipient" name="recipient" class="input" type="email" placeholder="john@example.com" required />
					</div>
					<div class="form-row">
						<label for="email_subject"><?php echo esc_html__( 'Subject', 'vortem-ai' ); ?> *</label>
						<input id="email_subject" name="email_subject" class="input" type="text" placeholder="<?php echo esc_attr__( 'Your subject', 'vortem-ai' ); ?>" required />
					</div>
					<div class="form-row">
						<label for="email_content"><?php echo esc_html__( 'Email Body', 'vortem-ai' ); ?> *</label>
						<?php
						$vortem_content   = '';
						$vortem_editor_id = 'email_content';
						$vortem_settings  = array(
							'textarea_name' => 'email_content',
							'textarea_rows' => 12,
							'media_buttons' => true,
							'quicktags'     => true,
							'tinymce'       => array(
								'toolbar1'    => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,spellchecker,fullscreen,wp_adv',
								'toolbar2'    => 'formatselect,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
								'content_css' => false,
								'setup'       => 'function(ed) { ed.on("change", function() { ed.save(); }); }',
							),
							'wpautop'       => false,
						);
						wp_editor( $vortem_content, $vortem_editor_id, $vortem_settings );
						?>
						<div class="hint"><?php echo esc_html__( 'You can use the visual editor or switch to text mode to paste HTML.', 'vortem-ai' ); ?></div>
					</div>
					<div class="form-actions">
						<button type="submit" class="btn btn-primary" id="send-email-btn"><?php echo esc_html__( 'Send Email', 'vortem-ai' ); ?></button>
						<button type="button" id="preview-email" class="btn"><?php echo esc_html__( 'Preview', 'vortem-ai' ); ?></button>
						<button type="reset" class="btn"><?php echo esc_html__( 'Clear', 'vortem-ai' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<!-- Email Sent Modal -->
		<div class="modal" id="email-sent-modal" aria-hidden="true" role="dialog" aria-labelledby="email-sent-modal-title">
			<div class="modal-backdrop" data-close="email-sent-modal"></div>
			<div class="modal-dialog alert-dialog">
				<div class="alert-header">
					<div class="alert-icon success">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<h3 id="email-sent-modal-title"><?php echo esc_html__( 'Email Sent', 'vortem-ai' ); ?></h3>
				</div>
				<div class="alert-body">
					<p id="email-sent-message"><?php echo esc_html__( 'Your email has been sent successfully.', 'vortem-ai' ); ?></p>
				</div>
				<div class="alert-actions">
					<button class="btn btn-primary" id="email-sent-ok">
						<?php echo esc_html__( 'OK', 'vortem-ai' ); ?>
						<span id="email-sent-countdown" style="margin-left: 8px; font-weight: normal; opacity: 0.7;"></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Email Preview Modal -->
		<div class="modal" id="email-preview-modal" aria-hidden="true" role="dialog" aria-labelledby="email-preview-modal-title">
			<div class="modal-backdrop" data-close="email-preview-modal"></div>
			<div class="modal-dialog email-preview-dialog">
				<div class="modal-header">
					<h3 id="email-preview-modal-title"><?php echo esc_html__( 'Email Preview', 'vortem-ai' ); ?></h3>
					<button class="btn btn-icon" id="close-email-preview-modal" aria-label="<?php echo esc_attr__( 'Close', 'vortem-ai' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
				<div class="email-preview-content">
					<div id="email-preview-root" class="email-preview-root"></div>
				</div>
			</div>
		</div>
	</div>
</div>
