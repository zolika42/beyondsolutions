window.onscroll = function() {onWindowScroll()};
function onWindowScroll() {
	const sections = document.querySelectorAll('section');
	const observer = new IntersectionObserver(entries => {
		entries.forEach(entry => {
		if (entry.isIntersecting) {
			entry.target.classList.add('visible');
		}
		});
	}, { threshold: 0.1 });
	sections.forEach(section => {
		observer.observe(section);
	});
}

function initLog() {
	// Select all dynamically rendered log messages
	const logMessages = document.querySelectorAll('.debug-log');
	// Add a click event listener to each log message
	logMessages.forEach(logMessage => {
		logMessage.addEventListener("click", () => {
			// Fade out the log message
			logMessage.style.transition = "opacity 0.5s";
			logMessage.style.opacity = "0";

			// Remove the log message from the DOM after the fade-out
			setTimeout(() => {
				logMessage.remove();
			}, 500);
		});
	});
}

function initScrollTop() {
	const scrollToTopButton = document.createElement('button');
	scrollToTopButton.className = 'scroll-to-top hidden';
	scrollToTopButton.innerHTML = '↑';
	document.body.appendChild(scrollToTopButton);

	scrollToTopButton.addEventListener('click', () => {
		window.scrollTo({ top: 0, behavior: 'smooth' });
	});

	window.addEventListener('scroll', () => {
		if (window.scrollY > 200) {
			scrollToTopButton.classList.remove('hidden');
			scrollToTopButton.classList.add('visible');
		} else {
			scrollToTopButton.classList.remove('visible');
			scrollToTopButton.classList.add('hidden');
		}
	});
}

function initTomSelect() {
	// Initialize Tom Select on the countryCode select dropdown
	new TomSelect("#countryCode",{
		create: true,
		sortField: {
			field: "text",
			direction: "asc"
		}
	});
}
document.addEventListener("DOMContentLoaded", () => {
	initLog();
	initScrollTop();
	initTomSelect();
});