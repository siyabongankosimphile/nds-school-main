document.addEventListener("DOMContentLoaded", function () {
    // Select all elements with class 'path'
    document.querySelectorAll(".path").forEach(function (item) {
        item.addEventListener("click", function () {
            let targetId = this.getAttribute("data-target");
            let targetElement = document.getElementById(targetId);

            // Hide all panels first
            document.querySelectorAll(".panel-content").forEach(panel => {
                panel.classList.add("hidden");
            });

            // Show the selected panel
            if (targetElement) {
                targetElement.classList.remove("hidden");
            }

            // Remove 'active' class from all paths
            document.querySelectorAll(".path").forEach(function (path) {
                path.classList.remove("active");
            });

            // Add 'active' class to the clicked path
            this.classList.add("active");
        });
    });

    // Get modal elements (may not exist on all pages)
    const openModalBtn = document.getElementById("openModalBtn");
    const modalOverlay = document.getElementById("modalOverlay");
    const modal = document.getElementById("modal");
    const closeBtn = document.getElementById("closeBtn");

    // Show modal when button is clicked
    if (openModalBtn && modalOverlay && modal) {
        openModalBtn.addEventListener("click", () => {
            modalOverlay.classList.remove("hidden");
            modal.classList.remove("hidden");
        });
    }

    // Close modal when clicking outside of modal overlay
    if (modalOverlay && modal) {
        modalOverlay.addEventListener("click", (event) => {
            if (event.target === modalOverlay) {
                modalOverlay.classList.add("hidden");
                modal.classList.add("hidden");
            }
        });
    }

    // Close modal when close button is clicked
    if (closeBtn && modalOverlay && modal) {
        closeBtn.addEventListener("click", () => {
            modalOverlay.classList.add("hidden");
            modal.classList.add("hidden");
        });
    }

    // Get all modal trigger buttons
    const openModalBtns = document.querySelectorAll(".open-modal");
    const closeModalBtns = document.querySelectorAll(".close-modal");
    const modalOverlays = document.querySelectorAll(".modal-overlay");

    // Show modal when any button is clicked
    openModalBtns.forEach((btn) => {
        btn.addEventListener("click", (event) => {
            const modalTarget = btn.getAttribute("data-modal-target");
            const modalOverlay = document.querySelector(`.${modalTarget}`);
            if (modalOverlay) {
                modalOverlay.classList.remove("hidden");
            }
        });
    });

    // Close modal when clicking outside or on close button
    modalOverlays.forEach((overlay) => {
        overlay.addEventListener("click", (event) => {
            if (event.target.classList.contains("modal-overlay")) {
                overlay.classList.add("hidden");
            }
        });
    });

    // Close modal when close button is clicked
    closeModalBtns.forEach((btn) => {
        btn.addEventListener("click", () => {
            const modalOverlay = btn.closest(".modal-overlay");
            if (modalOverlay) {
                modalOverlay.classList.add("hidden");
            }
        });
    });


    function addRow() {
        let table = document.getElementById("programTable").getElementsByTagName('tbody')[0];
        let newRow = table.insertRow();

        // Add new cells
        let cell1 = newRow.insertCell(0);
        let cell2 = newRow.insertCell(1);

        // Input field for program name
        cell1.innerHTML = '<input type="text" name="program_name[]" placeholder="Enter program name">';

        // Remove button
        cell2.innerHTML = '<button onclick="removeRow(this)">Remove</button>';
    }

    function removeRow(button) {
        let row = button.parentNode.parentNode;
        row.parentNode.removeChild(row);
    }

    function saveRows() {
        let programNames = document.querySelectorAll('input[name="program_name[]"]');
        let descriptions = document.querySelectorAll('input[name="description[]"]');
        let pathId = document.querySelector('input[name="path_id"]').value; // Assuming it's a single input field

        let data = [];

        programNames.forEach((program, index) => {
            if (descriptions[index] && program.value.trim() !== "") { // Ensure matching pairs and non-empty name
                data.push({
                    program_name: program.value,
                    program_description: descriptions[index].value,
                    path_id: pathId
                });
            }
        });

        if (data.length === 0) {
            console.log("No valid programs to save.");
            return;
        }

        console.log("Saving programs:", data);

        // Send data to backend
        fetch('program-functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    programs: data
                }) // Sending as an array
            })
            .then(response => response.json())
            .then(result => console.log("Saved successfully:", result))
            .catch(error => console.error("Error saving:", error));
    }

});