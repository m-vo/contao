import {Controller} from '@hotwired/stimulus';

export default class TabsController extends Controller {
    static targets = ['navigation', 'tab'];
    static instanceCount = 0;

    instanceId = undefined;
    lastTabId = 0;
    activeTab = null;

    initialize() {
        TabsController.instanceCount++;
        this.instanceId = TabsController.instanceCount;
    }

    tabTargetConnected(panel) {
        this.lastTabId++;

        const control_reference = `tab-control_${this.instanceId}_${this.lastTabId}`;
        const panel_reference = panel.id || `tab-panel_${this.instanceId}_${this.lastTabId}`;

        // Create navigation elements
        const selectButton = document.createElement('button');
        selectButton.id = control_reference;
        selectButton.className = 'select';
        selectButton.innerText = panel.dataset.label;
        selectButton.setAttribute('type', 'button');
        selectButton.setAttribute('role', 'tab');
        selectButton.setAttribute('aria-controls', panel_reference);

        selectButton.addEventListener('click', () => {
            this.selectTab(panel);
        })

        const closeButton = document.createElement('button');
        closeButton.className = 'close';
        closeButton.innerText = 'x';
        closeButton.setAttribute('type', 'button');
        closeButton.setAttribute('aria-controls', panel_reference);
        closeButton.setAttribute('aria-label', 'Close'); // todo i18n

        closeButton.addEventListener('click', () => {
            // Remove the panel and let the disconnect handler do the rest
            panel.remove();
        });

        const li = document.createElement('li');
        li.setAttribute('role', 'presentation');
        li.append(selectButton);
        li.append(closeButton);

        // Enhance panel container
        panel.id = panel_reference;
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', control_reference);

        // Add navigation element and activate the newly added tab
        this.navigationTarget.append(li);
        this.selectTab(panel);
    }

    tabTargetDisconnected(panel) {
        // Remove controls
        const li = document.getElementById(panel.getAttribute('aria-labelledby')).parentElement;
        li.remove();

        // Select the first tab/no tab if the current tab was active before closing.
        if (panel === this.activeTab) {
            if (this.hasTabTarget) {
                this.selectTab(this.tabTarget);
            } else {
                this.activeTab = null;
            }
        }
    }

    selectTab(panel) {
        this.tabTargets.forEach((el) => {
            const isTarget = el === panel;

            el.setAttribute('aria-selected', isTarget ? 'true' : 'false');
            el.style.display = isTarget ? 'revert' : 'none';

            const selectButton = document.getElementById(el.getAttribute('aria-labelledby'));
            selectButton?.setAttribute('aria-selected', isTarget);
            selectButton?.parentElement.classList.toggle('active', isTarget);
        });

        this.activeTab = panel;
    }
}
