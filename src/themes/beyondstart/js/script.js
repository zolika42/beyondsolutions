/**
 * Global onscroll event handler.
 * Calls the onWindowScroll function whenever the user scrolls.
 */
window.onscroll = function () {
	onWindowScroll();
};

/**
 * Observes all <section> elements and adds the "visible" class when they enter the viewport.
 * Uses the IntersectionObserver API with a threshold of 0.1 (10% visibility).
 */
function onWindowScroll() {
	const sections = document.querySelectorAll("section");
	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) {
					entry.target.classList.add("visible");
				}
			});
		},
		{ threshold: 0.1 }
	);
	sections.forEach((section) => {
		observer.observe(section);
	});
}

/**
 * Initializes the log display functionality.
 * Attaches a click event to the toggle log button that toggles the visibility of the log container.
 * If the log container becomes visible, it calls fetchLogs() to load log messages.
 *
 * @returns {boolean|undefined} Returns false if toggleLogButton is not found.
 */
function initLog() {
	const logContainer = document.getElementById("log-container");
	const toggleLogButton = document.getElementById("toggle-log");

	if (!toggleLogButton) {
		return false;
	}

	toggleLogButton.addEventListener("click", () => {
		logContainer.classList.toggle("visible");
		if (logContainer.classList.contains("visible")) {
			fetchLogs();
		}
	});
}

/**
 * Fetches log entries from the server.
 * It calls the endpoint with the number of lines requested and then renders the logs.
 *
 * @param {number} [lines=10] - The number of log lines to fetch.
 */
async function fetchLogs(lines = 10) {
	try {
		const response = await fetch(`index.php?endpoint=getLogs&lines=${lines}`);
		const data = await response.json();

		// Handle different possible structures of the response.
		if (Array.isArray(data)) {
			renderLogs(data);
		} else if (data.logs && Array.isArray(data.logs)) {
			renderLogs(data.logs);
		} else {
			addLogMessage("error", "Failed to fetch logs. Invalid data format.");
		}
	} catch (error) {
		addLogMessage("error", `Error fetching logs: ${error.message}`);
	}
}

/**
 * Renders log messages in the log container.
 * For each log entry, extracts the log level and message and then calls addLogMessage.
 *
 * @param {string[]} logs - An array of log entry strings.
 */
function renderLogs(logs) {
	const logElement = document.getElementById("log");
	logElement.innerHTML = "";

	logs.forEach((log) => {
		// Updated regex to capture the log level within "BeyondStartSolution.LEVEL"
		const match = log.match(/\.(info|warning|error):/i);
		const level = match ? match[1].toLowerCase() : "info";
		const message = log.replace(/\[.*?\]\s+[^\s]+\.(info|warning|error):/i, "").trim(); // Extract message

		// Use the extracted level and message
		addLogMessage(level, message);
	});
}

/**
 * Adds a log message to the log container with styling based on log level.
 * The log message div is also clickable to remove itself from the UI.
 *
 * @param {string} level - The log level (e.g., "info", "warning", "error").
 * @param {string} message - The log message to display.
 */
function addLogMessage(level, message) {
	const logMessage = document.createElement("div");
	logMessage.className = `debug-log ${level}`;
	logMessage.style.borderLeftColor = getLevelColor(level);
	logMessage.textContent = `[${level.toUpperCase()}] ${message}`;

	const logElement = document.getElementById("log");
	logElement.appendChild(logMessage);

	// Add click-to-remove functionality: fade out and remove on click.
	logMessage.addEventListener("click", () => {
		logMessage.style.opacity = "0";
		setTimeout(() => logMessage.remove(), 500);
	});
}

/**
 * Returns the color corresponding to a log level.
 *
 * @param {string} level - The log level ("info", "warning", "error").
 * @returns {string} - A hex color code.
 */
function getLevelColor(level) {
	const colors = {
		info: "#2b7a78",
		warning: "#f4a261",
		error: "#e63946",
	};
	return colors[level] || "#333";
}

/**
 * Initializes a scroll-to-top button.
 * The button appears when scrolling down 200px, and when clicked, smoothly scrolls the page back to the top.
 */
function initScrollTop() {
	const scrollToTopButton = document.createElement("button");
	scrollToTopButton.className = "scroll-to-top hidden";
	scrollToTopButton.innerHTML = "↑";
	document.body.appendChild(scrollToTopButton);

	scrollToTopButton.addEventListener("click", () => {
		window.scrollTo({ top: 0, behavior: "smooth" });
	});

	window.addEventListener("scroll", () => {
		if (window.scrollY > 200) {
			scrollToTopButton.classList.remove("hidden");
			scrollToTopButton.classList.add("visible");
		} else {
			scrollToTopButton.classList.remove("visible");
			scrollToTopButton.classList.add("hidden");
		}
	});
}

/**
 * Processes and displays contact form errors.
 * For each error, this function clears the field's value, sets the error message as a blinking placeholder,
 * and restores the original value after 5 seconds if no user input is detected.
 * It also scrolls the form into view if errors are present.
 */
function handleContactForm() {
	// --- Get the form element ---
	const form = document.querySelector(".contact-form");

	// --- Determine if errors exist ---
	// Global error (if any) and all field-specific error elements.
	const globalError = document.querySelector('.contact-form > div.form-error');
	const fieldErrors = document.querySelectorAll('.contact-form .form-error[data-field-name]');
	const errorsExist = globalError || fieldErrors.length > 0;

	// --- Remove any global error element (if present) ---
	if (globalError) {
		globalError.remove();
	}

	// --- Process each field error with a data-field-name attribute ---
	fieldErrors.forEach((errorEl) => {
		// Get the target field's name from the data-field-name attribute.
		const fieldName = errorEl.getAttribute("data-field-name");
		// Find the corresponding field (input, textarea, or select).
		const field = document.querySelector(`.contact-form [name="${fieldName}"]`);
		if (!field) return;

		// Save the original value in a data attribute.
		field.dataset.originalValue = field.value;
		// Clear the field's value.
		field.value = "";

		// Add a class so our CSS styles its placeholder red.
		field.classList.add("blink-placeholder");

		// Get the error text and set it as the placeholder.
		const errorText = errorEl.textContent.trim();
		field.placeholder = errorText;

		// Start an interval that toggles the placeholder text every 500ms.
		let showError = true;
		const blinkInterval = setInterval(() => {
			showError = !showError;
			field.placeholder = showError ? errorText : "";
		}, 500);

		// Remove the error element from the DOM.
		errorEl.remove();

		// Set a timeout for 5 seconds to restore the original value if no user input occurs.
		const restoreTimeout = setTimeout(() => {
			clearInterval(blinkInterval);
			// Ensure the error text stays visible (or you can clear it if desired)
			field.placeholder = errorText;
			field.classList.remove("blink-placeholder");
			if (field.value === "") {
				field.value = field.dataset.originalValue;
			}
		}, 5000);

		// If the user starts typing, cancel the blinking and timeout.
		field.addEventListener("input", function cancelBlink() {
			clearInterval(blinkInterval);
			clearTimeout(restoreTimeout);
			field.classList.remove("blink-placeholder");
			// Optionally, clear the placeholder once the user types.
			field.placeholder = "";
			field.removeEventListener("input", cancelBlink);
		});
	});

	// --- Scroll to the form if any errors were present ---
	if (errorsExist) {
		// Scroll the form into view (smoothly and centered on the screen)
		form.scrollIntoView({ behavior: "smooth", block: "center" });
	}
}

/**
 * Handles reCAPTCHA errors.
 * If a reCAPTCHA error is detected, the function hides the widget, displays a blinking error message
 * (occupying the same space), and after 5 seconds resets and restores the widget.
 */
function handleCaptchaError() {
	// Find the error element for the captcha.
	const captchaError = document.querySelector('.form-error[data-field-name="g-recaptcha-response"]');
	if (!captchaError) {
		// No captcha error to process.
		return;
	}

	// Find the reCAPTCHA container.
	const captchaContainer = document.querySelector(".g-recaptcha");

	// Hide the reCAPTCHA widget.
	if (captchaContainer) {
		captchaContainer.style.display = "none";
	}

	// Save the original error text.
	const originalText = captchaError.textContent;
	let blinkVisible = true;

	// Start blinking: toggle the text content every 500ms.
	const blinkInterval = setInterval(() => {
		blinkVisible = !blinkVisible;
		captchaError.textContent = blinkVisible ? originalText : "";
	}, 500);

	// After 5 seconds, stop blinking, reset the captcha, and show it again.
	setTimeout(() => {
		clearInterval(blinkInterval);
		// Make sure the error text is visible.
		captchaError.textContent = originalText;
		// Hide the error span.
		captchaError.style.display = "none";
		// Show the reCAPTCHA widget.
		if (captchaContainer) {
			captchaContainer.style.display = "block";
		}
		// Reset the reCAPTCHA widget.
		if (typeof grecaptcha !== "undefined") {
			grecaptcha.reset();
		}
	}, 5000);
}

/**
 * Initializes the TomSelect library on the "#countryCode" select field.
 * Configures it for single selection, disables creation of new items, and sets focus/blur behavior.
 *
 * @returns {TomSelect} - The initialized TomSelect instance.
 */
function initTomSelect() {
	return new TomSelect("#countryCode", {
		create: false, // Disable creation of new items
		maxItems: 1, // Single selection
		allowEmptyOption: true, // Allow the field to be empty
		placeholder: "Start typing...", // Placeholder text for the empty state
		items: ["hu (+36)"],

		onFocus: function () {
			this.clear(); // Clear the selected value when the field gains focus
		},

		onBlur: function () {
			if (!this.getValue()) {
				this.setTextboxValue(""); // Ensure no text remains when focus is lost
			}
		},

		onChange: function (value) {
			if (value) {
				document.getElementById("phone").focus(); // Automatically move focus to the phone field
			}
		},
	});
}

/**
 * Displays form errors by highlighting corresponding input fields and reCAPTCHA.
 * For each error:
 * - For reCAPTCHA, it appends a tooltip inside the widget.
 * - For other fields, it replaces the placeholder with the error message, clears the field,
 *   and applies a blinking effect.
 * Finally, it scrolls to the first field with an error.
 *
 * @param {Object} errors - An object mapping field names to error messages.
 */
function displayFormErrors(errors) {
	const inputFields = document.querySelectorAll(".contact-form input, .contact-form textarea");

	// Clear all previous errors.
	inputFields.forEach((input) => {
		input.classList.remove("error-field");
		input.dataset.originalPlaceholder = ""; // Reset placeholder backup.
		input.dataset.originalValue = ""; // Reset original value backup.
		const existingTooltip = input.parentElement.querySelector(".error-tooltip");
		if (existingTooltip) {
			existingTooltip.remove();
		}
	});

	const recaptchaContainer = document.querySelector(".g-recaptcha");
	const recaptchaTooltip = recaptchaContainer?.parentElement.querySelector(".error-tooltip");

	if (recaptchaTooltip) {
		recaptchaTooltip.remove();
	}

	let firstErrorField = null;

	// Loop through errors and apply messages to respective fields.
	Object.entries(errors).forEach(([field, message]) => {
		if (field === "g-recaptcha-response") {
			// Handle reCAPTCHA error.
			if (recaptchaContainer) {
				// Add a tooltip for the error.
				const tooltip = document.createElement("div");
				tooltip.className = "error-tooltip";
				tooltip.textContent = message;

				// Append tooltip as a child of reCAPTCHA container.
				recaptchaContainer.appendChild(tooltip);

				// Adjust tooltip position.
				const recaptchaBounds = recaptchaContainer.getBoundingClientRect();
				tooltip.style.margin = `150px 0 0 0`;

				// Set the first error field for scrolling.
				if (!firstErrorField) {
					firstErrorField = recaptchaContainer;
				}
			}
		} else {
			// Handle normal input fields.
			const inputField = document.querySelector(`[name='${field}']`);
			if (inputField) {
				// Highlight the field.
				inputField.classList.add("error-field");

				// Backup the current placeholder and value.
				inputField.dataset.originalPlaceholder = inputField.placeholder;
				inputField.dataset.originalValue = inputField.value;

				// Replace placeholder with the error message and clear value.
				inputField.placeholder = message;
				inputField.value = "";

				// Add a blinking effect to the field.
				blinkField(inputField);

				// Set the first error field for scrolling.
				if (!firstErrorField) {
					firstErrorField = inputField;
				}
			}
		}
	});

	// Scroll to the first error field if it exists.
	if (firstErrorField) {
		firstErrorField.scrollIntoView({ behavior: "smooth", block: "center" });
	}
}

/**
 * Applies a blinking effect to a field.
 * Adds the "blinking" class to the field, then removes it after 5 seconds.
 * Also restores the original placeholder and value if the field remains empty.
 *
 * @param {HTMLElement} field - The form field to blink.
 */
function blinkField(field) {
	field.classList.add("blinking"); // Add blinking class

	// Remove blinking effect after 5 seconds.
	setTimeout(() => {
		field.classList.remove("blinking");
	}, 5000);

	// Restore the original placeholder and value after 5 seconds (if no input).
	setTimeout(() => {
		if (field.dataset.originalPlaceholder) {
			field.placeholder = field.dataset.originalPlaceholder;
			field.dataset.originalPlaceholder = ""; // Clear the backup.
		}
		if (field.dataset.originalValue && field.value.length === 0) {
			field.value = field.dataset.originalValue;
			field.dataset.originalValue = ""; // Clear the backup.
		}
	}, 5000);
}

/**
 * Handles form submission by disabling the submit button, showing a spinner,
 * and then processing the success message if present.
 * The success message (in .form-success) will blink with green text and the same background as input fields.
 * After 10 seconds, the success message is removed and the form is reset.
 */
function initFormSubmitHandler() {
	const form = document.querySelector(".contact-form");
	if (!form) return;

	// Listen for the form submit event.
	form.addEventListener("submit", function (e) {
		const submitButton = form.querySelector('input[type="submit"]');
		// Disable the submit button.
		submitButton.disabled = true;

		// Create a spinner element.
		const spinner = document.createElement("span");
		spinner.className = "spinner";
		spinner.style.marginLeft = "10px";
		// Use a Unicode character or CSS-styled element for the spinner.
		spinner.innerHTML = "&#x21bb;"; // Clockwise open circle arrow; CSS animation will spin it.

		// Append the spinner next to the submit button.
		submitButton.parentNode.appendChild(spinner);
		submitButton.display = 'none';

		// Simulate AJAX submission delay.
		setTimeout(() => {
			// Here you would normally process your AJAX response.
			// For demonstration, assume submission is successful and set a success message.
			const successDiv = document.querySelector(".form-success");
			if (successDiv) {
				successDiv.textContent = "Your form was submitted successfully!";
			}

			// Handle success: blink success message and reset the form.

			// Remove the spinner and re-enable the submit button.
			spinner.remove();
			submitButton.disabled = false;
		}, 5000); // Adjust delay as needed for your actual submission process
	});
}

/**
 * Handles the display of a success message.
 * If the .form-success div is not empty, this function applies a blinking effect to the text,
 * setting the background to match input fields and text color to green.
 * The blinking continues for 10 seconds, after which the message is removed and the form is reset.
 */
function handleFormSuccess() {
	const successDiv = document.querySelector(".form-success");
	if (successDiv && successDiv.textContent.trim() !== "") {
		// Get the background color from an input element (assuming all inputs share the same background).
		const inputBg = getComputedStyle(document.querySelector(".contact-form input")).backgroundColor;
		successDiv.style.backgroundColor = inputBg;
		successDiv.style.color = "green";

		// Start blinking the success message.
		let visible = true;
		const blinkInterval = setInterval(() => {
			visible = !visible;
			successDiv.style.opacity = visible ? "1" : "0";
		}, 500);

		// After 10 seconds, stop blinking, clear the success message, and reset the form.
		setTimeout(() => {
			clearInterval(blinkInterval);
			successDiv.textContent = "";
			successDiv.style.opacity = "1"; // Reset opacity
		}, 10000);
		// Reset the form fields.
		const form = document.querySelector(".contact-form");
		if (form) {
			form.reset();
		}
		form.querySelectorAll('input, select, textarea').forEach(function(e) {
			if (e.getAttribute('type') !== 'submit') {
				e.value = ''
			}
		});
	}
}

/**
 * DOMContentLoaded event listener.
 * Once the document is fully loaded, this initializes:
 * - Log functionality,
 * - Scroll-to-top button,
 * - TomSelect on the country code field,
 * - Contact form error handling,
 * - reCAPTCHA error handling, and
 * - Form submission handling (spinner and success message blinking).
 */
document.addEventListener("DOMContentLoaded", () => {
	initLog();
	initScrollTop();
	initTomSelect();
	handleContactForm();
	handleCaptchaError();
	initFormSubmitHandler();
	handleFormSuccess();
});
