/**
 * Website-to-App Converter
 * Vanilla JavaScript / AJAX Application Script
 */

document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Handle AJAX calculation if pricing calculator element exists
    const calculatorContainer = document.querySelector('[data-calculator]');
    if (calculatorContainer) {
        const updateCalculation = async () => {
            const planId = calculatorContainer.querySelector('select[name="plan_id"]')?.value || 1;
            const themeId = calculatorContainer.querySelector('input[name="theme_id"]:checked')?.value || 1;
            const addonCheckboxes = calculatorContainer.querySelectorAll('input[name="addon_ids[]"]:checked');
            const addonIds = Array.from(addonCheckboxes).map(cb => cb.value);

            try {
                const response = await fetch('/api/pricing/calculate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ plan_id: planId, theme_id: themeId, addon_ids: addonIds })
                });
                const data = await response.json();
                if (data.success && data.calculation) {
                    const totalElem = calculatorContainer.querySelector('[data-total-amount]');
                    if (totalElem) {
                        totalElem.textContent = '₹' + Number(data.calculation.total_amount).toFixed(2);
                    }
                }
            } catch (err) {
                console.error('Failed to calculate pricing dynamically:', err);
            }
        };

        calculatorContainer.addEventListener('change', updateCalculation);
    }

    // ===================================================
    // FAQ Accordion Interaction
    // ===================================================
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const questionBtn = item.querySelector('.faq-question');
        if (!questionBtn) return;

        questionBtn.addEventListener('click', () => {
            const isActive = item.classList.contains('active');

            // Close all other FAQ items for a clean single-open accordion feel
            faqItems.forEach(other => {
                if (other !== item) {
                    other.classList.remove('active');
                    const otherBtn = other.querySelector('.faq-question');
                    if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
                }
            });

            // Toggle current item
            if (isActive) {
                item.classList.remove('active');
                questionBtn.setAttribute('aria-expanded', 'false');
            } else {
                item.classList.add('active');
                questionBtn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // ===================================================
    // Pricing Annual / Monthly Billing Switcher
    // ===================================================
    const pricingSwitch = document.getElementById('pricingSwitch');
    const priceVals = document.querySelectorAll('.pricing-card .price-val');
    const periodVals = document.querySelectorAll('.pricing-card .period');

    if (pricingSwitch && priceVals.length > 0) {
        pricingSwitch.addEventListener('click', () => {
            const isYearly = pricingSwitch.classList.toggle('active');
            pricingSwitch.setAttribute('aria-checked', isYearly ? 'true' : 'false');

            priceVals.forEach(val => {
                const targetPrice = isYearly ? val.dataset.yearly : val.dataset.monthly;
                if (targetPrice) {
                    // Smooth subtle fade effect on price number
                    val.style.opacity = '0';
                    setTimeout(() => {
                        val.textContent = targetPrice;
                        val.style.opacity = '1';
                    }, 150);
                }
            });

            periodVals.forEach(period => {
                period.textContent = isYearly ? '/ mo, billed yearly' : '/ month';
            });
        });
    }

    // ===================================================
    // Audit URL Auto-prefix helper
    // ===================================================
    const auditForm = document.getElementById('homeAuditForm');
    const auditInput = document.getElementById('homeAuditUrl');
    if (auditForm && auditInput) {
        auditForm.addEventListener('submit', (e) => {
            let val = auditInput.value.trim();
            if (val && !/^https?:\/\//i.test(val)) {
                auditInput.value = 'https://' + val;
            }
        });
    }

    // ===================================================
    // Smooth Sequential Entrance Animation for "From Website to App" Steps
    // ===================================================
    const howSection = document.getElementById('how-it-works') || document.querySelector('.how-it-works-section');
    if (howSection) {
        const stepCards = howSection.querySelectorAll('.step-card');
        if (stepCards.length > 0) {
            const triggerStepSequence = () => {
                stepCards.forEach((card, index) => {
                    setTimeout(() => {
                        card.classList.add('is-visible');
                    }, index * 320); // ~0.32s delay between consecutive steps
                });
            };

            const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReducedMotion || !('IntersectionObserver' in window)) {
                stepCards.forEach(card => card.classList.add('is-visible'));
            } else {
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            triggerStepSequence();
                            obs.unobserve(entry.target); // Runs only once when first entering viewport
                        }
                    });
                }, {
                    threshold: 0.15,
                    rootMargin: '0px 0px -20px 0px'
                });

                observer.observe(howSection);
            }
        }
    }

    // ===================================================
    // Stacked / Overlapping Row Entrance Animation for Features
    // ("Everything You Need in a Native App")
    // ===================================================
    const featuresSection = document.getElementById('features') || document.querySelector('.features-grid-section');
    const featuresGrid = featuresSection ? featuresSection.querySelector('.features-grid') : null;

    if (featuresSection && featuresGrid) {
        const featureCards = featuresGrid.querySelectorAll('.feature-card');
        const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (featureCards.length >= 6 && !prefersReducedMotion && ('IntersectionObserver' in window)) {
            // Apply initial stacked deck state
            featuresGrid.classList.add('features-deck-init');

            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        obs.unobserve(entry.target); // Play animation only once

                        // Row 1 spreads from stack into 3-column positions (~850ms duration)
                        setTimeout(() => {
                            featuresGrid.classList.add('spread-row-1');
                        }, 80);

                        // Row 2 starts after Row 1 settles + ~200ms delay (850ms + 200ms = 1050ms)
                        setTimeout(() => {
                            featuresGrid.classList.add('spread-row-2');
                        }, 1130);

                        // Once Row 2 settles completely, clean up temporary classes
                        // so cards permanently return to original untouched grid & hover state
                        setTimeout(() => {
                            featuresGrid.classList.remove('features-deck-init', 'spread-row-1', 'spread-row-2');
                        }, 2100);
                    }
                });
            }, {
                threshold: 0.15,
                rootMargin: '0px 0px -40px 0px'
            });

            observer.observe(featuresSection);
        }
    }
});
