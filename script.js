
document.addEventListener("DOMContentLoaded", function () {
    const dateInputs = document.querySelectorAll('input[type="date"]');

    dateInputs.forEach(function (input) {
        input.min = new Date().toISOString().split("T")[0];
    });

    const eventForms = document.querySelectorAll(".event-form");

    eventForms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            const startTime = form.querySelector('input[name="start_time"]').value;
            const endTime = form.querySelector('input[name="end_time"]').value;

            if (startTime >= endTime) {
                event.preventDefault();
                alert("End time must be later than start time.");
            }
        });
    });
});
