
(() => {
	const formatValue = (value) => {
		if (value >= 1000000) {
			return `${(value / 1000000).toFixed(1).replace(/\.0$/, '')}M`;
		}
		if (value >= 1000) {
			return `${(value / 1000).toFixed(1).replace(/\.0$/, '')}K`;
		}
		return `${value}`;
	};

	const navToggle = document.getElementById('sidebarToggle');
	const sidebar = document.getElementById('sidebar');
	if (navToggle && sidebar) {
		navToggle.addEventListener('click', () => {
			sidebar.classList.toggle('active');
		});

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !navToggle.contains(e.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
	}

	const copyBtn = document.querySelector('[data-copy-email]');
	if (copyBtn) {
		const defaultLabel = copyBtn.textContent?.trim() || 'Copy email';
		copyBtn.addEventListener('click', async () => {
			const email = copyBtn.getAttribute('data-email');
			if (!email) {
				return;
			}

			const fallbackCopy = () => {
				const textarea = document.createElement('textarea');
				textarea.value = email;
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild(textarea);
				textarea.focus();
				textarea.select();
				try {
					document.execCommand('copy');
				} catch (error) {
					console.warn('Copy failed', error);
				}
				document.body.removeChild(textarea);
			};

			try {
				if (navigator.clipboard && window.isSecureContext) {
					await navigator.clipboard.writeText(email);
				} else {
					fallbackCopy();
				}
				copyBtn.textContent = 'Copied!';
				setTimeout(() => {
					copyBtn.textContent = defaultLabel;
				}, 1500);
			} catch (error) {
				console.error('Clipboard error', error);
				fallbackCopy();
			}
		});
	}

	const statusFilter = document.getElementById('statusFilter');
	const appointmentRows = Array.from(document.querySelectorAll('[data-status-row]'));
	if (statusFilter && appointmentRows.length) {
		statusFilter.addEventListener('change', () => {
			const value = statusFilter.value;
			appointmentRows.forEach((row) => {
				const rowStatus = row.getAttribute('data-status');
				const showRow = value === 'all' || value === rowStatus;
				row.style.display = showRow ? '' : 'none';
			});
		});
	}

	const statCards = document.querySelectorAll('[data-kpi]');
	if (statCards.length && 'IntersectionObserver' in window) {
		const observer = new IntersectionObserver((entries, obs) => {
			entries.forEach((entry) => {
				if (!entry.isIntersecting) {
					return;
				}

				const card = entry.target;
				const value = parseInt(card.getAttribute('data-value') || '0', 10);
				const label = card.querySelector('.stat-value');
				if (!label || label.dataset.animated === 'true') {
					obs.unobserve(card);
					return;
				}

				label.dataset.animated = 'true';
				const duration = 800;
				const start = performance.now();

				const tick = (now) => {
					const progress = Math.min((now - start) / duration, 1);
					const current = Math.round(progress * value);
					label.textContent = formatValue(current);
					if (progress < 1) {
						requestAnimationFrame(tick);
					} else {
						label.textContent = formatValue(value);
					}
				};

				requestAnimationFrame(tick);
				obs.unobserve(card);
			});
		}, { threshold: 0.4 });

		statCards.forEach((card) => observer.observe(card));
	}
})();
