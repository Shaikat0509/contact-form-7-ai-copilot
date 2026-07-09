/**
 * Contact Form 7 AI Copilot — admin page behaviour.
 *
 * Vanilla JS, no framework or jQuery dependency. Only loaded on the
 * plugin's own settings page.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initPromptCounter();
		initTestConnection();
		initModelLoader();
		initSubmissionDetail();
	} );

	/**
	 * Posts to this plugin's shared AJAX endpoint and resolves with the
	 * parsed JSON response. Rejects only on a transport-level failure;
	 * an `{ success: false }` response still resolves so callers can
	 * read the error message.
	 *
	 * @param {string} action AJAX action name (see cf7aicAdmin.actions).
	 * @param {Object} params Additional POST parameters.
	 * @return {Promise<Object>}
	 */
	function postAjax( action, params ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', cf7aicAdmin.nonce );

		Object.keys( params || {} ).forEach( function ( key ) {
			body.set( key, params[ key ] );
		} );

		return fetch( cf7aicAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Wires up the live character counter and reset button on the Prompt tab.
	 * No-ops entirely if those elements are not present on the current tab.
	 */
	function initPromptCounter() {
		var textarea = document.getElementById( 'cf7aic_system_prompt' );
		var counter = document.getElementById( 'cf7aic-prompt-counter' );
		var resetButton = document.getElementById( 'cf7aic-reset-prompt' );

		if ( ! textarea || ! counter ) {
			return;
		}

		var maxLength = parseInt( textarea.getAttribute( 'data-max-length' ), 10 ) || 0;

		function updateCounter() {
			counter.textContent = textarea.value.length + ' / ' + maxLength;
		}

		textarea.addEventListener( 'input', updateCounter );
		updateCounter();

		if ( resetButton ) {
			resetButton.addEventListener( 'click', function () {
				textarea.value = textarea.getAttribute( 'data-default-prompt' ) || '';
				updateCounter();
				textarea.focus();
			} );
		}
	}

	/**
	 * Wires up the "Test Connection" button on the AI Provider tab.
	 * No-ops entirely if the button is not present on the current tab.
	 */
	function initTestConnection() {
		var button = document.getElementById( 'cf7aic-test-connection' );
		var result = document.getElementById( 'cf7aic-test-connection-result' );
		var providerField = document.getElementById( 'cf7aic_provider' );
		var apiKeyField = document.getElementById( 'cf7aic_api_key' );
		var modelField = document.getElementById( 'cf7aic_model' );

		if ( ! button || ! result || 'undefined' === typeof cf7aicAdmin ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			result.textContent = cf7aicAdmin.strings.testing;
			result.className = 'cf7aic-test-result';

			postAjax( cf7aicAdmin.actions.testConnection, {
				provider: providerField ? providerField.value : '',
				api_key: apiKeyField ? apiKeyField.value : '',
				model: modelField ? modelField.value : ''
			} )
				.then( function ( json ) {
					var message = ( json && json.data && json.data.message ) ? json.data.message : cf7aicAdmin.strings.genericError;
					result.textContent = message;
					result.className = 'cf7aic-test-result ' + ( json && json.success ? 'cf7aic-test-result--success' : 'cf7aic-test-result--error' );
				} )
				.catch( function () {
					result.textContent = cf7aicAdmin.strings.genericError;
					result.className = 'cf7aic-test-result cf7aic-test-result--error';
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	}

	/**
	 * Wires up the Model dropdown on the AI Provider tab: a "Load Models"
	 * button, plus automatic fetches when a key is already saved, when the
	 * provider changes, or right after a new key is typed. No-ops entirely
	 * if the elements are not present on the current tab.
	 */
	function initModelLoader() {
		var select = document.getElementById( 'cf7aic_model' );
		var loadButton = document.getElementById( 'cf7aic-load-models' );
		var status = document.getElementById( 'cf7aic-model-status' );
		var providerField = document.getElementById( 'cf7aic_provider' );
		var apiKeyField = document.getElementById( 'cf7aic_api_key' );

		if ( ! select || ! loadButton || 'undefined' === typeof cf7aicAdmin ) {
			return;
		}

		function loadModels() {
			var currentModel = select.getAttribute( 'data-current-model' ) || '';

			loadButton.disabled = true;
			status.textContent = cf7aicAdmin.strings.loadingModels;
			status.className = 'description';

			postAjax( cf7aicAdmin.actions.listModels, {
				provider: providerField ? providerField.value : '',
				api_key: apiKeyField ? apiKeyField.value : ''
			} )
				.then( function ( json ) {
					if ( ! json || ! json.success || ! json.data || ! Array.isArray( json.data.models ) ) {
						status.textContent = ( json && json.data && json.data.message ) ? json.data.message : cf7aicAdmin.strings.genericError;
						status.className = 'description cf7aic-test-result--error';
						return;
					}

					var models = json.data.models;
					var hasCurrent = models.some( function ( m ) { return m.id === currentModel; } );

					select.innerHTML = '';

					if ( currentModel && ! hasCurrent ) {
						var keepOption = document.createElement( 'option' );
						keepOption.value = currentModel;
						keepOption.textContent = currentModel;
						select.appendChild( keepOption );
					}

					models.forEach( function ( model ) {
						var option = document.createElement( 'option' );
						option.value = model.id;
						option.textContent = model.label || model.id;
						if ( model.id === currentModel ) {
							option.selected = true;
						}
						select.appendChild( option );
					} );

					status.textContent = models.length + ' ' + ( 1 === models.length ? 'model' : 'models' ) + ' loaded.';
					status.className = 'description cf7aic-test-result--success';
				} )
				.catch( function () {
					status.textContent = cf7aicAdmin.strings.genericError;
					status.className = 'description cf7aic-test-result--error';
				} )
				.finally( function () {
					loadButton.disabled = false;
				} );
		}

		loadButton.addEventListener( 'click', loadModels );

		if ( providerField ) {
			providerField.addEventListener( 'change', function () {
				if ( '1' === loadButton.getAttribute( 'data-has-api-key' ) || ( apiKeyField && apiKeyField.value ) ) {
					loadModels();
				}
			} );
		}

		if ( apiKeyField ) {
			apiKeyField.addEventListener( 'blur', function () {
				if ( apiKeyField.value ) {
					loadModels();
				}
			} );
		}

		if ( '1' === loadButton.getAttribute( 'data-has-api-key' ) ) {
			loadModels();
		}
	}

	/**
	 * Wires up the Submission Details review screen: Save Draft, Send
	 * Reply (with its confirmation dialog), Mark Reviewed, Archive, and
	 * Delete. No-ops entirely if the page's root element is not present
	 * (i.e. on any other section/tab).
	 */
	function initSubmissionDetail() {
		var root = document.querySelector( '.cf7aic-detail-grid' );

		if ( ! root || 'undefined' === typeof cf7aicAdmin ) {
			return;
		}

		var submissionId = root.getAttribute( 'data-submission-id' );
		var textarea = document.getElementById( 'cf7aic-reply-textarea' );
		var replyStatus = document.getElementById( 'cf7aic-reply-status' );

		var saveDraftButton = document.getElementById( 'cf7aic-save-draft' );
		var sendReplyButton = document.getElementById( 'cf7aic-send-reply' );
		var confirmDialog = document.getElementById( 'cf7aic-send-confirm-dialog' );
		var confirmSendButton = document.getElementById( 'cf7aic-confirm-send' );
		var markReviewedButton = document.getElementById( 'cf7aic-mark-reviewed' );
		var archiveButton = document.getElementById( 'cf7aic-archive' );
		var deleteButton = document.getElementById( 'cf7aic-delete' );

		/**
		 * Runs a workflow action (mark reviewed / archive / delete) and
		 * reloads or redirects on success so the page always reflects
		 * the fresh database state rather than trying to replicate it in JS.
		 *
		 * @param {HTMLElement} button
		 * @param {string} action
		 * @param {Object} extraParams
		 * @param {Function} onSuccess
		 */
		function runAction( button, action, extraParams, onSuccess ) {
			button.disabled = true;

			var params = Object.assign( { id: submissionId }, extraParams || {} );

			postAjax( action, params )
				.then( function ( json ) {
					if ( json && json.success ) {
						onSuccess();
						return;
					}

					window.alert( ( json && json.data && json.data.message ) ? json.data.message : cf7aicAdmin.strings.genericError );
					button.disabled = false;
				} )
				.catch( function () {
					window.alert( cf7aicAdmin.strings.genericError );
					button.disabled = false;
				} );
		}

		if ( saveDraftButton && textarea && replyStatus ) {
			saveDraftButton.addEventListener( 'click', function () {
				saveDraftButton.disabled = true;
				replyStatus.textContent = cf7aicAdmin.strings.saving;
				replyStatus.className = 'cf7aic-test-result';

				postAjax( cf7aicAdmin.actions.saveDraft, { id: submissionId, reply: textarea.value } )
					.then( function ( json ) {
						var message = ( json && json.data && json.data.message ) ? json.data.message : cf7aicAdmin.strings.genericError;
						replyStatus.textContent = message;
						replyStatus.className = 'cf7aic-test-result ' + ( json && json.success ? 'cf7aic-test-result--success' : 'cf7aic-test-result--error' );
					} )
					.catch( function () {
						replyStatus.textContent = cf7aicAdmin.strings.genericError;
						replyStatus.className = 'cf7aic-test-result cf7aic-test-result--error';
					} )
					.finally( function () {
						saveDraftButton.disabled = false;
					} );
			} );
		}

		if ( sendReplyButton && confirmDialog && 'function' === typeof confirmDialog.showModal ) {
			sendReplyButton.addEventListener( 'click', function () {
				confirmDialog.showModal();
			} );
		}

		if ( confirmSendButton && textarea && replyStatus && confirmDialog ) {
			confirmSendButton.addEventListener( 'click', function () {
				confirmSendButton.disabled = true;
				replyStatus.textContent = cf7aicAdmin.strings.sending;
				replyStatus.className = 'cf7aic-test-result';

				postAjax( cf7aicAdmin.actions.sendReply, { id: submissionId, reply: textarea.value } )
					.then( function ( json ) {
						var message = ( json && json.data && json.data.message ) ? json.data.message : cf7aicAdmin.strings.genericError;

						if ( json && json.success ) {
							confirmDialog.close();
							window.location.reload();
							return;
						}

						replyStatus.textContent = message;
						replyStatus.className = 'cf7aic-test-result cf7aic-test-result--error';
					} )
					.catch( function () {
						replyStatus.textContent = cf7aicAdmin.strings.genericError;
						replyStatus.className = 'cf7aic-test-result cf7aic-test-result--error';
					} )
					.finally( function () {
						confirmSendButton.disabled = false;
					} );
			} );
		}

		if ( markReviewedButton ) {
			markReviewedButton.addEventListener( 'click', function () {
				runAction( markReviewedButton, cf7aicAdmin.actions.markReviewed, {}, function () {
					window.location.reload();
				} );
			} );
		}

		if ( archiveButton ) {
			archiveButton.addEventListener( 'click', function () {
				runAction( archiveButton, cf7aicAdmin.actions.archive, {}, function () {
					window.location.reload();
				} );
			} );
		}

		if ( deleteButton ) {
			deleteButton.addEventListener( 'click', function () {
				if ( ! window.confirm( cf7aicAdmin.strings.confirmDelete ) ) {
					return;
				}

				runAction( deleteButton, cf7aicAdmin.actions['delete'], {}, function () {
					window.location.href = cf7aicAdmin.inboxUrl;
				} );
			} );
		}
	}
} )();
