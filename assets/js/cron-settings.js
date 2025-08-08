/**
 * Cron Settings JavaScript
 * Handles the interval slider and cron job settings
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const cronEnabledToggle = document.getElementById('cronEnabledToggle');
    const intervalSlider = document.getElementById('intervalSlider');
    const intervalValue = document.getElementById('intervalValue');
    const intervalUnit = document.getElementById('intervalUnit');
    
    // Update interval display when slider changes
    if (intervalSlider) {
        intervalSlider.addEventListener('input', function() {
            updateIntervalDisplay(this.value);
        });
        
        // Initial update
        updateIntervalDisplay(intervalSlider.value);
    }
    
    // Toggle slider enabled/disabled state based on toggle switch
    if (cronEnabledToggle && intervalSlider) {
        cronEnabledToggle.addEventListener('change', function() {
            intervalSlider.disabled = !this.checked;
            intervalSlider.parentElement.classList.toggle('opacity-50', !this.checked);
            
            // Update the form's hidden input for submission
            document.getElementById('cronEnabled').value = this.checked ? '1' : '0';
        });
        
        // Initial state
        intervalSlider.disabled = !cronEnabledToggle.checked;
        intervalSlider.parentElement.classList.toggle('opacity-50', !cronEnabledToggle.checked);
    }
    
    /**
     * Update the interval display based on slider value
     * @param {number} value - The slider value (2-120 minutes)
     */
    function updateIntervalDisplay(value) {
        // Convert to number
        const minutes = parseInt(value, 10);
        
        // Update hidden input for form submission
        document.getElementById('cronInterval').value = minutes;
        
        // Update display
        if (minutes < 60) {
            intervalValue.textContent = minutes;
            intervalUnit.textContent = minutes === 1 ? 'minute' : 'minutes';
        } else {
            const hours = minutes / 60;
            intervalValue.textContent = hours;
            intervalUnit.textContent = hours === 1 ? 'hour' : 'hours';
        }
        
        // Update color class based on speed
        intervalValue.classList.remove('fast', 'medium', 'slow');
        if (minutes <= 10) {
            intervalValue.classList.add('fast');
        } else if (minutes <= 30) {
            intervalValue.classList.add('medium');
        } else {
            intervalValue.classList.add('slow');
        }
        
        // Add animation class
        intervalValue.classList.add('updating');
        setTimeout(() => {
            intervalValue.classList.remove('updating');
        }, 500);
        
        // Update slider color (this is handled by CSS gradient)
    }
    
    // Save settings via AJAX when form is submitted
    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            // The form will be submitted normally, no need to prevent default
            // The server-side code will handle the cron job scheduling
        });
    }
});
