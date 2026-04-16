/**
 * Modal Auto-Selection Helper
 * Automatically pre-selects form fields based on URL parameters
 * 
 * Usage:
 * - Add data-auto-select="field_name" to any select element
 * - The field will be auto-selected based on URL parameter with same name
 * - Visual feedback will be added to indicate pre-selection
 */

class ModalAutoSelect {
    constructor() {
        this.init();
    }

    init() {
        // Add event listeners to all modal triggers
        document.addEventListener('DOMContentLoaded', () => {
            this.bindModalTriggers();
        });
    }

    bindModalTriggers() {
        // Find all modal links and add click handlers
        const modalLinks = document.querySelectorAll('a[href*="Modal"]');
        modalLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href && href.startsWith('#')) {
                    const modalId = href.substring(1);
                    this.handleModalOpen(modalId);
                }
            });
        });
    }

    handleModalOpen(modalId) {
        // Small delay to ensure modal is visible
        setTimeout(() => {
            this.autoSelectFields(modalId);
        }, 100);
    }

    autoSelectFields(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        // Find all elements with data-auto-select attribute
        const autoSelectElements = modal.querySelectorAll('[data-auto-select]');
        
        autoSelectElements.forEach(element => {
            const fieldName = element.getAttribute('data-auto-select');
            const urlValue = this.getUrlParameter(fieldName);
            
            if (urlValue) {
                this.selectField(element, urlValue, fieldName);
            }
        });
    }

    getUrlParameter(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    selectField(element, value, fieldName) {
        // Set the value
        element.value = value;
        
        // Add visual feedback based on field type
        const colorClass = this.getColorClass(fieldName);
        element.classList.add(`${colorClass}-50`, `border-${colorClass}-300`);
        
        // Add indicator
        this.addIndicator(element, fieldName, colorClass);
    }

    getColorClass(fieldName) {
        // Define color schemes for different field types
        const colorMap = {
            'faculty_id': 'blue',
            'program_id': 'green',
            'course_id': 'purple',
            'staff_id': 'orange',
            'student_id': 'indigo'
        };
        
        return colorMap[fieldName] || 'blue';
    }

    addIndicator(element, fieldName, colorClass) {
        // Remove existing indicator
        const existingIndicator = element.parentNode.querySelector(`.text-${colorClass}-600`);
        if (existingIndicator) {
            existingIndicator.remove();
        }

        // Create new indicator
        const indicator = document.createElement('div');
        indicator.className = `text-xs text-${colorClass}-600 mt-1 flex items-center`;
        
        const fieldDisplayName = this.getFieldDisplayName(fieldName);
        indicator.innerHTML = `<i class="fas fa-info-circle mr-1"></i>Pre-selected ${fieldDisplayName} from current context`;
        
        element.parentNode.appendChild(indicator);
    }

    getFieldDisplayName(fieldName) {
        const displayNames = {
            'faculty_id': 'Faculty',
            'program_id': 'Program',
            'course_id': 'Course',
            'staff_id': 'Staff Member',
            'student_id': 'Student'
        };
        
        return displayNames[fieldName] || fieldName;
    }

    // Method to reset modal when closed
    resetModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        // Reset form
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }

        // Remove visual indicators
        const autoSelectElements = modal.querySelectorAll('[data-auto-select]');
        autoSelectElements.forEach(element => {
            const fieldName = element.getAttribute('data-auto-select');
            const colorClass = this.getColorClass(fieldName);
            
            element.classList.remove(`${colorClass}-50`, `border-${colorClass}-300`);
            
            const indicator = element.parentNode.querySelector(`.text-${colorClass}-600`);
            if (indicator) {
                indicator.remove();
            }
        });
    }
}

// Initialize the auto-select functionality
const modalAutoSelect = new ModalAutoSelect();

// Export for use in other scripts
window.ModalAutoSelect = modalAutoSelect;



