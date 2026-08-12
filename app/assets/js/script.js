console.log('Custom js linked.');

// ----------------------------------------
// MOBILE NAVIGATION MENU
// Handles the mobile burger menu, keeps its accessibility state in sync,
// prevents background scrolling while open, and allows keyboard closing.
// ----------------------------------------

const burgerBtn = document.getElementById("burger-btn");
const mainNavMenu = document.getElementById("main-nav");
const header = document.getElementById('header');

let headerHeight = 0;

// Keep the JS state aligned with the default aria-expanded="false" value set in the HTML.
let mobileMenuIsOpen = false;

if (burgerBtn && mainNavMenu && header) {

    function updateHeaderHeight() {
        // Read the current header height and keep headerHeight updated.
        headerHeight = header.offsetHeight;

        // Pass the current height to the --header-height CSS variable, which defines the top position
        // of the mobile navigation menu. Subtract 2px so the menu also covers the header's bottom border.
        document.documentElement.style.setProperty('--header-height', `${headerHeight - 2}px`);
    }

    // Set the correct header height when the page loads.
    updateHeaderHeight();

    // Recalculate it whenever the viewport size changes.
    window.addEventListener('resize', updateHeaderHeight);

    // Toggle the mobile navigation menu when the burger button is clicked.
    burgerBtn.addEventListener("click", () => {
        mainNavMenu.classList.toggle("hidden");

        // Keep track of the menu state by switching between false and true.
        mobileMenuIsOpen = !mobileMenuIsOpen;

        // Keep aria-expanded synchronized with the current menu state.
        burgerBtn.setAttribute('aria-expanded', String(mobileMenuIsOpen));

        // Lock/unlock background page scrolling according to the menu state.
        document.body.classList.toggle('no-scroll', mobileMenuIsOpen);
    });

    // Close the mobile menu with Escape for keyboard accessibility.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileMenuIsOpen) {
            mainNavMenu.classList.add("hidden");
            mobileMenuIsOpen = false;
            burgerBtn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('no-scroll');
        }
    });
}

// ----------------------------------------
// HELP MESSAGE
// Handles the contextual help message shared across pages: it can be toggled from the help button
// and closed through its close button, the Escape key, or a click outside the message.
// Each page contains at most one help message, so the same IDs/classes can be reused consistently.
// ----------------------------------------

const helpBtn = document.getElementById('help-btn');
const helpMessage = document.getElementById('help-message');
const closeHelpIcon = document.getElementById('close-help-message-btn');

if (helpBtn && helpMessage && closeHelpIcon) {

    // Helper functions for explicitly showing and hiding the help message.
    function openHelpMessage() {
        helpMessage.classList.remove("hidden");
        helpBtn.setAttribute('aria-expanded', 'true');
    }

    function closeHelpMessage(restoreFocus = false) {
        helpMessage.classList.add("hidden");
        helpBtn.setAttribute('aria-expanded', 'false');

        if (restoreFocus) {
            helpBtn.focus();
        }
    }

    // Toggle the help message when the help button is clicked.
    helpBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (helpMessage.classList.contains('hidden')) {
            openHelpMessage();
        } else {
            closeHelpMessage();
        }
    });

    // Close the help message when its X button is clicked.
    closeHelpIcon.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        closeHelpMessage(true);
    });

    // Close the help message with the Escape key.
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && !helpMessage.classList.contains('hidden')) {
            closeHelpMessage(true);
        }
    });

    // Close the help message when the user clicks outside both the message and its trigger button.
    document.addEventListener("click", (e) => {
        const clickedInside =
            helpMessage.contains(e.target) ||
            helpBtn.contains(e.target);

        if (!clickedInside) {
            closeHelpMessage();
        }
    });
}
