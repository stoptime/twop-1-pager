// TODO:
// 2. Have boxes at botton with page, time/parser, web storage, recently parsed?
// 3. Make the butt0n the spinner: "is-loading"

export class TWOP {
	constructor(parser) {
		this.parser = parser + '?url=';

		this.iconColors = {
			green  : 'has-text-success',
			yellow : 'has-text-warning',
			red    : 'has-text-danger'
		};

		this.iconTypes = {
			info    : 'fa-info-circle',
			check   : 'fa-check',
			warning : 'fa-exclamation-triangle',
			error   : 'fa-ban'
		};

		this.input = document.querySelector('#input');
		this.searchButton = document.querySelector('#search');
		this.clearButton = document.querySelector('#clear');
		this.rightIcon = document.querySelector('.is-right');
		this.rightIconItalic = document.querySelector('.is-right i');
		this.resultsDiv = document.querySelector('#results');
		this.url = this.input.getAttribute('placeholder');
	}

	// https://bulma.io/documentation/modifiers/responsive-helpers/
	resizeClasses() {
		let buttonDiv = document.querySelector('#button-toggle');
		let formField = document.querySelector('#form-field');
		let hasIcons = document.querySelector('#has-icons');
		if (window.innerWidth < 768) {
			document.querySelectorAll('.is-medium').forEach((el) => {
				el.classList.add('removed-medium');
				el.classList.remove('is-medium');
			});
			buttonDiv.classList.remove('control');
			buttonDiv.classList.add('column');
			formField.classList.remove('has-addons', 'box');
			hasIcons.classList.remove('has-icons-left');
		}
		else {
			document.querySelectorAll('.removed-medium').forEach((el) => {
				el.classList.add('is-medium');
				el.classList.remove('removed-medium');
			});
			buttonDiv.classList.remove('column');
			buttonDiv.classList.add('control');
			formField.classList.add('has-addons', 'box');
			hasIcons.classList.add('has-icons-left');
		}
	}

	resetForm = (addDefautCheck = true, clearInput = false) => {
		const { rightIcon, rightIconItalic, iconColors, iconTypes, enableSearch, input } = this;

		for (const color in iconColors) {
			rightIcon.classList.remove(iconColors[color]);
		}

		for (const type in iconTypes) {
			rightIconItalic.classList.remove(iconTypes[type]);
		}

		if (addDefautCheck) {
			rightIconItalic.classList.add(iconTypes.check);
		}

		if (clearInput) {
			input.value = '';
			this.url = input.getAttribute('placeholder');
		}
		enableSearch();
	};

	updateRightIconColor = (color) => {
		this.rightIcon.classList.add(this.iconColors[color]);
	};

	updateRightIcon = (icon) => {
		this.rightIconItalic.classList.add(this.iconTypes[icon]);
	};

	validTwopUrl(url) {
		switch (url.host) {
			case 'brilliantbutcancelled.com':
			case 'www.brilliantbutcancelled.com':
				if (url.pathname.startsWith('/show')) {
					return true;
				}
			default:
				return false;
		}
	}

	formError = () => {
		// reset the form, but keep the current right icon
		this.resetForm(false);
		this.updateRightIconColor('red');
		this.updateRightIcon('error');
		this.disableSearch();
	};

	disableSearch = () => {
		this.searchButton.disabled = true;
	};

	enableSearch = () => {
		this.searchButton.disabled = false;
		this.searchButton.classList.remove('is-loading');
	};

	validUrl = () => {
		const { input, validTwopUrl, resetForm, updateRightIcon, updateRightIconColor, formError } = this;

		if (!input.value) {
			resetForm();
			this.url = input.getAttribute('placeholder');
			return;
		}
		try {
			const url = new URL(input.value);
			if (validTwopUrl(url)) {
				this.url = url;
				resetForm(false);
				updateRightIcon('check');
				updateRightIconColor('green');
			}
			else {
				formError();
			}
		} catch (error) {
			formError();
		}
	};

	submittedNotice = (url) => {
		const p = document.createElement('p');
		p.id = 'stand-by';
		p.innerHTML = `1-page-i-fying <a href="${url}" target="_blank">${url}</a> - stand by...`;
		this.searchButton.classList.add('is-loading');
		this.resultsDiv.appendChild(p);
	};

	doSearch = (event) => {
		const { parser, disableSearch, resultsDiv, submittedNotice, enableSearch, resetForm } = this;

		disableSearch();
		event.preventDefault();

		resultsDiv.innerHTML = '';

		submittedNotice(this.url);

		const urlEncoded = encodeURIComponent(this.url);
		const eventSourceUrl = parser + urlEncoded;
		const source = new EventSource(eventSourceUrl);

		source.addEventListener('total-pages', function(eventStream) {
			let totalPages = parseInt(eventStream.data);
			const progressDiv = document.createElement('div');
			const progressP = document.createElement('p');
			const countP = document.createElement('p');
			countP.innerHTML = `Page Count: ${totalPages}`;
			progressP.id = 'counter';
			progressDiv.classList.add('content', 'is-large');
			progressP.innerHTML = `<b>${totalPages}</b> pages left to parse.`;
			document.querySelector('#stand-by').remove();
			progressDiv.appendChild(countP);
			progressDiv.appendChild(progressP);
			resultsDiv.appendChild(progressDiv);
			window.i = setInterval(() => {
				if (totalPages > 0 && document.querySelector('#counter b')) {
					document.querySelector('#counter b').innerText = totalPages--;
				} 
				else {
					document.querySelector('#counter b').innerText = '0';
					clearInterval(i);
				}
			}, 150);
		});

		source.addEventListener('parsing-page', function(eventStream) {
			const progressVal = window.totalPages - parseInt(eventStream.data);
			setTimeout(() => {
				document.querySelector('#counter b').innerText = progressVal;
			}, 120);
		});

		source.addEventListener('pre-done', function(eventStream) {
			clearInterval(window.i);
			delete window.i;
			resultsDiv.innerHTML = '';
		});

		source.addEventListener('done', function(eventStream) {
			source.close();
			enableSearch();
		});

		source.addEventListener('not-found', function(eventStream) {
			document.querySelector('#stand-by').remove();
			const div = document.createElement('div');
			div.innerHTML = eventStream.data;
			resultsDiv.appendChild(div);
			source.close();
			resetForm();
		});

		source.addEventListener('message', function(eventStream) {
			const p = document.createElement('p');
			p.innerHTML = eventStream.data;
			resultsDiv.appendChild(p);
		});

		source.addEventListener('error', function(eventStream) {
			if (document.querySelector('#stand-by')) {
				document.querySelector('#stand-by').remove();
			}
			// stop any setInterval
			if (window.i) {
				clearInterval(window.i);
				delete window.i;
			}
			const div = document.createElement('div');
			const inputValue = document.querySelector('input').value;
			div.classList.add('notification', 'is-danger');
			div.innerHTML = `URL: <a target="_blank" href="${inputValue}">${inputValue}</a> could not be reached, is it valid?`;
			resultsDiv.appendChild(div);
			console.error('EventSource failed:', eventStream);
			source.close();
			resetForm(true, true);
		});
	};
}
