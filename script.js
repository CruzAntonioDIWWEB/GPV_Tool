/**
 * This script populates the PDV dropdown based on the selected GPV
*/

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    const gpvSelect = document.getElementById('gpv');
    const pdvSelect = document.getElementById('pdv');
    
    // Function to populate PDV dropdown based on selected GPV
    function updatePdvDropdown() {
        const selectedGpv = gpvSelect.value;
        
        // Clear current PDV options
        pdvSelect.innerHTML = '<option value="" disabled selected>Seleccione un PDV</option>';
        
        // If no GPV is selected, disable PDV dropdown
        if (!selectedGpv) {
            pdvSelect.disabled = true;
            pdvSelect.innerHTML = '<option value="" disabled selected>Seleccione primero un GPV</option>';
            return;
        }
        
        // Get PDVs for the selected GPV
        const pdvList = gpvPdvData[selectedGpv];
        
        // If the GPV has no PDVs, show a message
        if (!pdvList || pdvList.length === 0) {
            pdvSelect.disabled = true;
            pdvSelect.innerHTML = '<option value="" disabled selected>No hay PDVs para este GPV</option>';
            return;
        }
        
        // Enable the PDV dropdown
        pdvSelect.disabled = false;
        
        // Populate with PDVs for this GPV (sorted alphabetically)
        pdvList.sort().forEach(function(pdv) {
            const option = document.createElement('option');
            option.value = pdv;
            option.textContent = pdv;
            pdvSelect.appendChild(option);
        });
    }
    
    // Listen for changes in GPV dropdown
    gpvSelect.addEventListener('change', updatePdvDropdown);
    
    // If there's a pre-selected GPV (after form submission), populate PDVs
    if (gpvSelect.value) {
        updatePdvDropdown();
        
        // If there's also a pre-selected PDV value, set it
        const currentPdv = pdvSelect.getAttribute('data-current-pdv');
        if (currentPdv) {
            pdvSelect.value = currentPdv;
        }
    }
});