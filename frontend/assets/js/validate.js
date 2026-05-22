/* Validation helpers (regex-based) */
(function () {
	const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{8,}$/; // min 8, letters + numbers
	const phoneRegex = /^\+?[0-9]{7,15}$/; // optional +, 7-15 digits

	function _getShowMessage() {
		if (typeof showMessage === 'function') return showMessage;
		return function (msg, type = 'info') {
			const div = document.getElementById('message');
			if (!div) return;
			div.textContent = msg;
			div.className = 'message ' + (type || '');
			div.style.display = 'block';
		};
	}

	const _show = _getShowMessage();

	window.validateEmail = function (email) {
		return emailRegex.test(String(email || '').trim());
	};

	window.validatePassword = function (password) {
		return passwordRegex.test(String(password || ''));
	};

	window.validatePhone = function (phone) {
		if (!phone) return true; // optional
		return phoneRegex.test(String(phone).trim());
	};

	window.validateFullName = function (name) {
		if (!name) return false;
		return String(name).trim().split(/\s+/).length >= 2;
	};

	window.validateLoginFields = function (email, password) {
		if (!validateEmail(email)) {
			_show('Please enter a valid email address', 'error');
			return false;
		}
		if (!password || String(password).trim().length === 0) {
			_show('Please enter your password', 'error');
			return false;
		}
		return true;
	};

	window.validateRegisterFields = function (fullName, email, password, confirmPassword, phone) {
		if (!validateFullName(fullName)) {
			_show('Please enter your full name (first and last)', 'error');
			return false;
		}
		if (!validateEmail(email)) {
			_show('Please enter a valid email address', 'error');
			return false;
		}
		if (password !== confirmPassword) {
			_show('Passwords do not match', 'error');
			return false;
		}
		if (!validatePassword(password)) {
			_show('Password must be at least 8 characters and include letters and numbers', 'error');
			return false;
		}
		if (phone && !validatePhone(phone)) {
			_show('Please enter a valid phone number', 'error');
			return false;
		}
		return true;
	};
})();

