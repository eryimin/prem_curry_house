
/* ==========================================
   PREM CURRY HOUSE
   MENU ORDERING + FOOD RECOMMENDATIONS
   ========================================== */

const modal = document.getElementById('food-modal');
const form = document.getElementById('food-modal-form');
const options = document.getElementById('pch-modal-options');

let current = null;

// ==========================================
// OPEN FOOD ORDER POPUP
// ==========================================

document.querySelectorAll('[data-food]').forEach(button => {

    button.addEventListener('click', () => {

        current = JSON.parse(button.dataset.food);

        form.reset();

        document.getElementById('pch-modal-title').textContent =
            current.name;

        document.getElementById('pch-modal-base').textContent =
            'Base price: RM ' + Number(current.price).toFixed(2);

        document.getElementById('pch-modal-id').value =
            current.id;

        options.textContent = '';

        // ==================================
        // FOOD IMAGE
        // ==================================

        const modalImage =
            document.getElementById('pch-modal-image');

        if (modalImage) {

            modalImage.style.display = 'none';
            modalImage.removeAttribute('src');

            modalImage.onerror = function () {
                this.style.display = 'none';
            };

            if (current.image) {

                modalImage.onload = function () {
                    this.style.display = 'block';
                };

                modalImage.src = current.image;
            }
        }

        // ==================================
        // RECOMMENDED ADD-ONS
        // ==================================

        const suggestionsBox =
            document.getElementById('pch-modal-suggestions');

        const recommendSection =
            document.getElementById('pch-recommend-section');

        if (suggestionsBox) {

            suggestionsBox.textContent = '';

            const suggestions = current.suggestions || [];

            suggestions.forEach(extra => {

                const label =
                    document.createElement('label');

                label.className = 'pch-suggestion-line';

                const checkbox =
                    document.createElement('input');

                checkbox.type = 'checkbox';
                checkbox.name = 'suggestions[]';
                checkbox.value = extra.id;
                checkbox.dataset.price = extra.price;

                const name =
                    document.createElement('span');

                name.textContent = extra.name;

                const price =
                    document.createElement('strong');

                price.textContent =
                    '+RM ' + Number(extra.price).toFixed(2);

                label.append(checkbox, name, price);

                suggestionsBox.appendChild(label);

            });

            // Only show recommendations if available.
            if (recommendSection) {
                recommendSection.hidden =
                    suggestions.length === 0;
            }
        }

        // ==================================
        // PRODUCT CUSTOMISATION OPTIONS
        // ==================================

        Object.entries(current.options || {}).forEach(
            ([group, choices]) => {

                const label =
                    document.createElement('label');

                label.textContent = group;

                const select =
                    document.createElement('select');

                select.name = `options[${group}]`;

                Object.entries(choices).forEach(
                    ([choice, price]) => {

                        const option =
                            document.createElement('option');

                        option.value = choice;

                        option.textContent =
                            choice +
                            (
                                Number(price) > 0
                                    ? ' (+RM ' +
                                      Number(price).toFixed(2) +
                                      ')'
                                    : ''
                            );

                        option.dataset.delta = Number(price);

                        select.appendChild(option);

                    }
                );

                options.append(label, select);
            }
        );

        calculateTotal();

        modal.showModal();

    });

});

// ==========================================
// CLOSE POPUP
// ==========================================

document
    .getElementById('pch-modal-close')
    .addEventListener('click', () => {
        modal.close();
    });

modal.addEventListener('click', event => {
    if (event.target === modal) {
        modal.close();
    }
});

// ==========================================
// CALCULATE ORDER TOTAL
// ==========================================

function calculateTotal() {

    if (!current) return;

    let optionsTotal = 0;

    // Calculate customisation prices.
    options.querySelectorAll('select').forEach(select => {

        optionsTotal += Number(
            select.selectedOptions[0]?.dataset.delta || 0
        );

    });

    // Get quantity.
    const quantityInput =
        document.getElementById('pch-modal-qty');

    const quantity = Math.max(
        1,
        Math.min(
            20,
            Number(quantityInput.value) || 1
        )
    );

    // Calculate recommended add-ons.
    let suggestionsTotal = 0;

    document
        .querySelectorAll(
            '#pch-modal-suggestions input:checked'
        )
        .forEach(checkbox => {

            suggestionsTotal += Number(
                checkbox.dataset.price || 0
            );

        });

    // Main food quantity applies to the main food
    // and its customisations.
    // Recommended products are separate cart items.
    const mainTotal =
        (Number(current.price) + optionsTotal) * quantity;

    const finalTotal =
        mainTotal + suggestionsTotal;

    document.getElementById('pch-modal-total').textContent =
        'RM ' + finalTotal.toFixed(2);
}

// Update total when selections change.
form.addEventListener('input', calculateTotal);
form.addEventListener('change', calculateTotal);

// ==========================================
// STICKY MENU CATEGORY HIGHLIGHT
// ==========================================

const anchors = [
    ...document.querySelectorAll('.pch-menu-side a')
];

const sections = [
    ...document.querySelectorAll('.pch-food-section')
];

function updateActiveCategory() {

    let chosen = sections[0];

    for (const section of sections) {

        if (section.getBoundingClientRect().top < 180) {
            chosen = section;
        }
    }

    anchors.forEach(anchor => {

        const active =
            chosen &&
            anchor.dataset.category ===
            chosen.dataset.category;

        anchor.classList.toggle('active', active);

        if (active) {
            anchor.setAttribute(
                'aria-current',
                'location'
            );
        } else {
            anchor.removeAttribute('aria-current');
        }

    });
}

window.addEventListener(
    'scroll',
    updateActiveCategory,
    { passive: true }
);

updateActiveCategory();
