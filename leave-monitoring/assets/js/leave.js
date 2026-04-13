document.addEventListener('DOMContentLoaded', function() {
    // Helper for leave reason shortcuts
    function getReasonShortcut(reason) {
        if (!reason) return '';
        const shortcuts = {
            'Sick leave without pay': 'SWOP',
            'Sick leave with pay': 'SLWP',
            'Vacation leave without pay': 'VWOP',
            'Vacation leave with pay': 'VLWP',
            'Maternity leave': 'ML',
            'Study leave': 'STUDY',
            'Wellness leave': 'WL',
            'Special privilege leave': 'SPL',
            'Forced leave': 'FL'
        };
        for (const [full, short] of Object.entries(shortcuts)) {
            if (reason.trim().toLowerCase() === full.toLowerCase()) return short;
        }
        return reason;
    }

    // Toggle "Others" input for Reason
    const reasonSelect = document.getElementById('leaveReason');
    const otherInput = document.getElementById('otherReason');
    
    if (reasonSelect && otherInput) {
        reasonSelect.addEventListener('change', function() {
            const payStatus = document.getElementById('payStatus');

            if (this.value === 'Others') {
                otherInput.classList.remove('d-none');
                otherInput.required = true;
            } else {
                otherInput.classList.add('d-none');
                otherInput.required = false;
            }

            // Auto-select pay status based on reason
            const withoutPayReasons = ['Sick leave without pay', 'Vacation leave without pay'];
            const withPayReasons = ['Sick leave with pay', 'Vacation leave with pay'];

            if (payStatus) {
                if (withoutPayReasons.includes(this.value)) {
                    payStatus.value = 'Without Pay';
                } else if (withPayReasons.includes(this.value)) {
                    payStatus.value = 'With Pay';
                }
            }
        });
    }

    // Auto-capitalize (Uppercase) Name Fields
    const nameFields = ['surname', 'first_name', 'middle_initial'];
    nameFields.forEach(fieldName => {
        const input = document.querySelector(`input[name="${fieldName}"]`);
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
            // Also add CSS via JS for immediate visual transform if needed, 
            // though 'input' event is fast enough.
            input.style.textTransform = 'uppercase';
        }
    });

    // Auto calculate days difference
    const fromDate = document.getElementById('leaveFrom');
    const toDate = document.getElementById('leaveTo');
    const totalDays = document.getElementById('totalDays');

    if (fromDate && toDate && totalDays) {
        function calculateDays() {
            if (fromDate.value && toDate.value) {
                const start = new Date(fromDate.value);
                const end = new Date(toDate.value);
                start.setHours(0,0,0,0);
                end.setHours(0,0,0,0);
                
                if (end >= start) {
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    totalDays.value = diffDays;
                } else {
                    totalDays.value = 'Invalid';
                }
            } else {
                totalDays.value = '0';
            }
        }

        fromDate.addEventListener('change', calculateDays);
        toDate.addEventListener('change', calculateDays);
    }

    // AJAX Save (Two-Stage Process)
    document.getElementById('saveLeaveBtn').addEventListener('click', function() {
        const form = document.getElementById('employeeLeaveForm');
        const leaveSection = document.getElementById('leaveParticularsSection');
        const btn = this;
        const employeeIdInput = document.getElementById('newEmployeeId');

        // Basic validation
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const isStage1 = leaveSection.classList.contains('d-none');
        const formData = new FormData(form);

        btn.disabled = true;
        btn.innerHTML = isStage1 ? '<i class="fas fa-spinner fa-spin me-2"></i>Registering Employee...' : '<i class="fas fa-spinner fa-spin me-2"></i>Saving Leave Record...';

        fetch('../actions/save-leave.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const alertContainer = document.getElementById('alertContainer');
            if (data.status === 'success') {
                if (isStage1) {
                    // Stage 1 Complete: Employee Saved
                    employeeIdInput.value = data.employee_id;
                    
                    // Show leave particulars
                    leaveSection.classList.remove('d-none');
                    
                    // Update UI to reflect we are in Step 2
                    const surname = form.querySelector('[name="surname"]').value;
                    const firstName = form.querySelector('[name="first_name"]').value;
                    const fullName = `${firstName} ${surname}`;
                    
                    document.getElementById('addEmployeeLeaveModalLabel').innerHTML = `<i class="fas fa-user-check me-2"></i> ${fullName} - <span class="text-warning">Add Leave</span>`;
                    
                    // Show a mini success for employee save
                    alertContainer.innerHTML = '<div class="alert alert-info alert-dismissible fade show"><i class="fas fa-info-circle me-2"></i>Employee profile registered. Reloading to update table...</div>';
                    
                    form.reset();
                    setTimeout(() => location.reload(), 800);
                } else {
                    // Stage 2 Complete: Leave Saved
                    alertContainer.innerHTML = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addEmployeeLeaveModal'));
                    modal.hide();
                    form.reset();
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                alertContainer.innerHTML = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
        })
        .catch(error => {
            console.error('Save Error:', error);
            document.getElementById('alertContainer').innerHTML = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>An error occurred: ' + error.message + '. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        })
        .finally(() => {
            btn.disabled = false;
        });
    });

    // Row Click Handler (for Opening View Modal)
    document.querySelector('#leaveMonitoringTable').addEventListener('click', function(e) {
        const row = e.target.closest('.clickable-row');
        if (!row) return;

        // Don't trigger if clicking buttons, links or checkboxes
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input[type="checkbox"]')) {
            return;
        }

        // Trigger the view button in this row
        const viewBtn = row.querySelector('.view-btn');
        if (viewBtn) {
            viewBtn.click();
        }
    });

    // Edit Employee Button Handler
    document.querySelectorAll('.edit-employee-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row click
            
            const employeeId = this.dataset.employeeId;
            const surname = this.dataset.surname;
            const firstName = this.dataset.firstname;
            const mi = this.dataset.mi;
            const dob = this.dataset.dob;
            const pob = this.dataset.pob;
            const empNo = this.dataset.employeeno;
            const schoolLevel = this.dataset.schoollevel;
            const status = this.dataset.status;

            const form = document.getElementById('employeeLeaveForm');
            document.getElementById('newEmployeeId').value = employeeId;
            form.querySelector('[name="surname"]').value = surname;
            form.querySelector('[name="first_name"]').value = firstName;
            form.querySelector('[name="middle_initial"]').value = mi;
            form.querySelector('[name="dob"]').value = (dob && dob !== '0000-00-00') ? dob : '';
            form.querySelector('[name="pob"]').value = pob;
            form.querySelector('[name="employee_no"]').value = empNo;
            if (form.querySelector('[name="school_level"]')) {
                form.querySelector('[name="school_level"]').value = schoolLevel || 'ES';
            }
            if (form.querySelector('[name="status"]')) {
                form.querySelector('[name="status"]').value = status || 'Active';
            }

            // Show employee info, hide leave particulars
            const leaveSection = document.getElementById('leaveParticularsSection');
            const employeeSection = document.getElementById('employeeDetailsSection');
            if (leaveSection) leaveSection.classList.add('d-none');
            if (employeeSection) employeeSection.classList.remove('d-none');
            
            // Update Modal Title
            const fullName = `${firstName} ${surname}`;
            const title = document.getElementById('addEmployeeLeaveModalLabel');
            if (title) title.innerHTML = `<i class="fas fa-user-edit me-2"></i> Edit Employee: ${fullName}`;
            
            // Update Save Button
            const saveBtn = document.getElementById('saveLeaveBtn');
            if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Profile';

            // Show Modal
            const addModal = new bootstrap.Modal(document.getElementById('addEmployeeLeaveModal'));
            addModal.show();
        });
    });
    const employeeInfoFields = document.querySelectorAll('#employeeLeaveForm [required]');
    employeeInfoFields.forEach(field => {
        field.addEventListener('click', function() {
            const employeeIdInput = document.getElementById('newEmployeeId');
            const leaveSection = document.getElementById('leaveParticularsSection');
            if (employeeIdInput.value && leaveSection.classList.contains('d-none')) {
                leaveSection.classList.remove('d-none');
                document.getElementById('saveLeaveBtn').innerHTML = '<i class="fas fa-save me-2"></i>Save Leave Record';
            }
        });
    });
    // Add Leave Button Handler
    document.querySelectorAll('.add-leave-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Avoid row click
            
            const employeeId = this.dataset.employeeId;
            const surname = this.dataset.surname;
            const firstName = this.dataset.firstname;
            const mi = this.dataset.mi;
            const dob = this.dataset.dob;
            const pob = this.dataset.pob;
            const empNo = this.dataset.employeeno;
            const schoolLevel = this.dataset.schoollevel;
            const status = this.dataset.status;

            const form = document.getElementById('employeeLeaveForm');
            document.getElementById('newEmployeeId').value = employeeId;
            form.querySelector('[name="surname"]').value = surname;
            form.querySelector('[name="first_name"]').value = firstName;
            form.querySelector('[name="middle_initial"]').value = mi;
            form.querySelector('[name="dob"]').value = (dob && dob !== '0000-00-00') ? dob : '';
            form.querySelector('[name="pob"]').value = pob;
            form.querySelector('[name="employee_no"]').value = empNo;
            if (form.querySelector('[name="school_level"]')) {
                form.querySelector('[name="school_level"]').value = schoolLevel || 'ES';
            }
            if (form.querySelector('[name="status"]')) {
                form.querySelector('[name="status"]').value = status || 'Active';
            }

            // Show leave particulars section, hide employee details
            const leaveSection = document.getElementById('leaveParticularsSection');
            const employeeSection = document.getElementById('employeeDetailsSection');
            if (leaveSection) leaveSection.classList.remove('d-none');
            if (employeeSection) employeeSection.classList.add('d-none');
            
            // Update Modal Title
            const fullName = `${firstName} ${surname}`;
            const title = document.getElementById('addEmployeeLeaveModalLabel');
            if (title) title.innerHTML = `<i class="fas fa-plus-circle me-2"></i> Add Leave: ${fullName}`;
            
            // Update Save Button
            const saveBtn = document.getElementById('saveLeaveBtn');
            if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Leave Record';

            // Show Modal
            const addModal = new bootstrap.Modal(document.getElementById('addEmployeeLeaveModal'));
            addModal.show();
        });
    });

    // Reset Add/Edit Modal on Close
    const addEmpModalEl = document.getElementById('addEmployeeLeaveModal');
    if (addEmpModalEl) {
        addEmpModalEl.addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('employeeLeaveForm');
            if (form) form.reset();
            document.getElementById('newEmployeeId').value = '';
            
            // Default sections: Show Employee, Hide Leave
            const leaveSection = document.getElementById('leaveParticularsSection');
            const employeeSection = document.getElementById('employeeDetailsSection');
            if (leaveSection) leaveSection.classList.add('d-none');
            if (employeeSection) employeeSection.classList.remove('d-none');
            
            // Reset title and button
            const title = document.getElementById('addEmployeeLeaveModalLabel');
            if (title) title.innerHTML = '<i class="fas fa-user-plus me-2"></i>Add Employee';
            const saveBtn = document.getElementById('saveLeaveBtn');
            if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Record';
        });
    }

    // View Modal Population
    const viewButtons = document.querySelectorAll('.view-btn');
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row click
            document.getElementById('viewExcelSurname').textContent = this.dataset.surname;
            document.getElementById('viewExcelFirstName').textContent = this.dataset.firstname;
            document.getElementById('viewExcelMi').textContent = this.dataset.mi;
            
             const surname = this.dataset.surname;
             const firstname = this.dataset.firstname;
             const mi = this.dataset.mi || '';

             // Set Static Fields from dataset (for immediate display)
             document.getElementById('viewExcelSurname').textContent = surname;
             document.getElementById('viewExcelFirstName').textContent = firstname;
             document.getElementById('viewExcelMi').textContent = mi;

             // Clear Table
             const tableBody = document.getElementById('viewLeaveTableBody');
             tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-2"><i class="fas fa-spinner fa-spin me-2"></i>Loading history...</td></tr>';

             // Fetch History
             fetch(`../actions/get-employee-history.php?surname=${encodeURIComponent(surname)}&first_name=${encodeURIComponent(firstname)}&mi=${encodeURIComponent(mi)}`)
                 .then(response => {
                     if (!response.ok) throw new Error('Network response was not ok');
                     return response.json();
                 })
                  .then(data => {
                      if (data.status === 'success') {
                          const emp = data.employee;
                          currentViewEmployee = emp; // Store for "Add Leave" button
                          // Update birth info from DB
                          let dobText = 'N/A';
                          if (emp.dob && emp.dob !== '0000-00-00') {
                              const d = new Date(emp.dob);
                              dobText = String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
                          }
                          document.getElementById('viewExcelDob').textContent = dobText;
                          document.getElementById('viewExcelPob').textContent = emp.pob || 'N/A';
                          document.getElementById('viewExcelEmpNo').textContent = emp.employee_no || '';

                          currentHistory = data.leaves;
                          renderHistoryItems(currentHistory);
                          
                          // Populate Year Filter
                          const yearFilter = document.getElementById('historyYearFilter');
                          if (yearFilter) {
                              const years = [...new Set(data.leaves.map(l => l.period_from ? new Date(l.period_from).getFullYear() : null).filter(y => y))].sort((a,b) => b-a);
                              yearFilter.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y}">${y}</option>`).join('');
                          }

                          // Reset filter inputs
                          if (document.getElementById('historySearch')) document.getElementById('historySearch').value = '';
                          if (document.getElementById('historyYearFilter')) document.getElementById('historyYearFilter').value = '';
                          if (document.getElementById('historyReasonFilter')) document.getElementById('historyReasonFilter').value = '';

                      } else {
                          document.getElementById('viewLeaveTableBody').innerHTML = `<tr><td colspan="7" class="text-center text-danger py-2">Error: ${data.message}</td></tr>`;
                      }
                  })
                  .catch(err => {
                      document.getElementById('viewLeaveTableBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger py-2">Failed to load history.</td></tr>';
                      console.error(err);
                  });

              const viewModal = new bootstrap.Modal(document.getElementById('viewLeaveModal'));
              viewModal.show();
         });
    });

    // Add Leave from View Modal button listener
    const addFromViewBtn = document.getElementById('addLeaveFromViewBtn');
    if (addFromViewBtn) {
        addFromViewBtn.addEventListener('click', function() {
            if (!currentViewEmployee) return;

            const emp = currentViewEmployee;
            const form = document.getElementById('addLeaveParticularsForm');
            
            // Populate employee ID
            document.getElementById('historyEmployeeId').value = emp.id;
            
            // Reset form fields
            form.reset();
            document.getElementById('historyOtherReason').classList.add('d-none');

            // Update modal title with employee name
            const fullName = `${emp.first_name} ${emp.surname}`;
            document.getElementById('addLeaveParticularsModalLabel').innerHTML = `<i class="fas fa-plus-circle me-2"></i> Add Leave Particulars: <span class="text-white fw-bold">${fullName}</span>`;

            // Open the new focused modal (DON'T close the view modal)
            const addPartModal = new bootstrap.Modal(document.getElementById('addLeaveParticularsModal'));
            addPartModal.show();
        });
    }

    // Logic for Add Leave Particulars Modal (History View)
    const historyReasonSelect = document.getElementById('historyLeaveReason');
    const historyOtherInput = document.getElementById('historyOtherReason');
    const historyPayStatus = document.getElementById('historyPayStatus');

    if (historyReasonSelect && historyOtherInput) {
        historyReasonSelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                historyOtherInput.classList.remove('d-none');
                historyOtherInput.required = true;
            } else {
                historyOtherInput.classList.add('d-none');
                historyOtherInput.required = false;
            }

            // Auto-select pay status based on reason
            const withoutPayReasons = ['Sick leave without pay', 'Vacation leave without pay'];
            const withPayReasons = ['Sick leave with pay', 'Vacation leave with pay'];

            if (historyPayStatus) {
                if (withoutPayReasons.includes(this.value)) {
                    historyPayStatus.value = 'Without Pay';
                } else if (withPayReasons.includes(this.value)) {
                    historyPayStatus.value = 'With Pay';
                }
            }
        });
    }

    // Days calculation for history modal
    const historyFromDate = document.getElementById('historyLeaveFrom');
    const historyToDate = document.getElementById('historyLeaveTo');
    const historyTotalDays = document.getElementById('historyTotalDays');

    if (historyFromDate && historyToDate && historyTotalDays) {
        function calculateHistoryDays() {
            if (historyFromDate.value && historyToDate.value) {
                const start = new Date(historyFromDate.value);
                const end = new Date(historyToDate.value);
                start.setHours(0,0,0,0);
                end.setHours(0,0,0,0);
                
                if (end >= start) {
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    historyTotalDays.value = diffDays;
                } else {
                    historyTotalDays.value = 'Invalid';
                }
            } else {
                historyTotalDays.value = '0';
            }
        }
        historyFromDate.addEventListener('change', calculateHistoryDays);
        historyToDate.addEventListener('change', calculateHistoryDays);
    }

    // Save logic for history modal
    const saveHistoryBtn = document.getElementById('saveHistoryLeaveBtn');
    if (saveHistoryBtn) {
        saveHistoryBtn.addEventListener('click', function() {
            const form = document.getElementById('addLeaveParticularsForm');
            const btn = this;

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            // If others is selected, replace reason with other_reason
            if (formData.get('reason') === 'Others') {
                formData.set('reason', formData.get('other_reason'));
            }
            // Add total_days to formData since it's a readonly input (might not be in FD)
            formData.append('total_days', document.getElementById('historyTotalDays').value);

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

            fetch('../actions/save-leave.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Success!
                    const addPartModal = bootstrap.Modal.getInstance(document.getElementById('addLeaveParticularsModal'));
                    if (addPartModal) addPartModal.hide();
                    
                    // Show success alert
                    const alertContainer = document.getElementById('alertContainer');
                    if (alertContainer) {
                        alertContainer.innerHTML = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }
                    
                    // Reload the history view or page
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                alert('An error occurred while saving the record.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Leave Record';
            });
        });
    }

    // History Table Filtering Logic
    function initializeHistoryFilters() {
        const historySearch = document.getElementById('historySearch');
        const historyYear = document.getElementById('historyYearFilter');
        const historyReason = document.getElementById('historyReasonFilter');
        const resetBtn = document.getElementById('resetHistoryFilters');

        if (historySearch && historyYear && historyReason) {
            const filterEvents = ['input', 'change'];
            [historySearch, historyYear, historyReason].forEach(el => {
                filterEvents.forEach(evt => {
                    el.addEventListener(evt, filterHistory);
                });
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                if (historySearch) historySearch.value = '';
                if (historyYear) historyYear.value = '';
                if (historyReason) historyReason.value = '';
                renderHistoryItems(currentHistory);
            });
        }
    }

    function filterHistory() {
        const historySearch = document.getElementById('historySearch');
        const historyYear = document.getElementById('historyYearFilter');
        const historyReason = document.getElementById('historyReasonFilter');

        const searchTerm = historySearch ? historySearch.value.toLowerCase().trim() : '';
        const selectedYear = historyYear ? historyYear.value : '';
        const selectedReason = historyReason ? historyReason.value : '';

        const filtered = currentHistory.filter(leaf => {
            const matchesSearch = !searchTerm || 
                (leaf.reason && leaf.reason.toLowerCase().includes(searchTerm)) ||
                (leaf.station && leaf.station.toLowerCase().includes(searchTerm)) ||
                (leaf.remarks && leaf.remarks.toLowerCase().includes(searchTerm));
            
            const matchesYear = !selectedYear || 
                (leaf.period_from && new Date(leaf.period_from).getFullYear().toString() === selectedYear);
            
            const standardReasons = [
                'Sick leave without pay', 'Sick leave with pay',
                'Vacation leave without pay', 'Vacation leave with pay',
                'Maternity leave', 'Study leave', 'Wellness leave',
                'Special privilege leave', 'Forced leave'
            ];
            
            let matchesReason = !selectedReason;
            if (selectedReason === 'Others') {
                matchesReason = leaf.reason && !standardReasons.includes(leaf.reason);
            } else if (selectedReason) {
                matchesReason = (leaf.reason === selectedReason);
            }

            return matchesSearch && matchesYear && matchesReason;
        });

        renderHistoryItems(filtered);
    }

    function renderHistoryItems(items) {
        const tableBody = document.getElementById('viewLeaveTableBody');
        if (!tableBody) return;
        tableBody.innerHTML = '';

        if (items.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-secondary">No matching records found.</td></tr>';
            return;
        }

        items.forEach(leaf => {
            let fromDate = '';
            if (leaf.period_from) {
                const df = new Date(leaf.period_from);
                fromDate = String(df.getDate()).padStart(2, '0') + '/' + String(df.getMonth() + 1).padStart(2, '0') + '/' + df.getFullYear();
            }
            let toDate = 'N/A';
            if (leaf.period_to && leaf.period_to !== '0000-00-00') {
                const dt = new Date(leaf.period_to);
                if (!isNaN(dt)) {
                    toDate = String(dt.getDate()).padStart(2, '0') + '/' + String(dt.getMonth() + 1).padStart(2, '0') + '/' + dt.getFullYear();
                }
            }

            const showDays = (toDate !== 'N/A' && leaf.total_days);
            const withoutPayVal = (leaf.pay_status === 'Without Pay' && showDays) ? leaf.total_days + ' day(s)' : '';
            const withPayVal = (leaf.pay_status === 'With Pay' && showDays) ? leaf.total_days + ' day(s)' : '';

            const row = `
                <tr style="height: 30px;">
                    <td>${fromDate}</td>
                    <td>${toDate}</td>
                    <td>${getReasonShortcut(leaf.reason)}</td>
                    <td>${leaf.station || ''}</td>
                    <td>${withoutPayVal}</td>
                    <td>${withPayVal}</td>
                    <td>${leaf.remarks || ''}</td>
                    <td class="d-print-none text-center">
                        <button type="button" class="btn btn-sm btn-outline-warning py-0 px-1" onclick="editRecordFromHistory(${leaf.id})" title="Edit Record"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteRecordFromHistory(${leaf.id})" title="Delete Record"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>
            `;
            tableBody.innerHTML += row;
        });

        // Add spacer rows for aesthetics
        if (items.length < 3) {
            for(let i=0; i < (3 - items.length); i++) {
                tableBody.innerHTML += '<tr style="height: 30px;"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            }
        }
    }

    // Call initialization
    initializeHistoryFilters();

    // Redundant row click listener removed
    let currentHistory = [];
    let currentViewEmployee = null;

    window.editRecordFromHistory = function(id) {
        const record = currentHistory.find(r => r.id == id);
        if (!record) return;

        // Populate Edit Modal
        // Map the plain object to the structure expected by populateEditModal
        const mappedData = {
            id: record.id,
            employee_id: record.employee_id,
            employee_no: record.employee_no,
            surname: record.surname || document.getElementById('viewExcelSurname').innerText,
            first_name: record.first_name || document.getElementById('viewExcelFirstName').innerText,
            middle_initial: record.middle_initial || document.getElementById('viewExcelMi').innerText,
            dob: record.date_of_birth || '',
            pob: record.place_of_birth || '',
            period_from: record.period_from,
            period_to: record.period_to,
            station: record.station,
            total_days: record.total_days,
            remarks: record.remarks,
            reason: record.reason,
            pay_status: record.pay_status
        };

        populateEditModal(mappedData, 'leave');

        // Hide the view modal first
        const viewModalEl = document.getElementById('viewLeaveModal');
        const viewModal = bootstrap.Modal.getInstance(viewModalEl);
        if (viewModal) {
            viewModal.hide();
        }

        // Show edit modal with a slight delay to allow backdrop transition
        setTimeout(() => {
            const editModal = new bootstrap.Modal(document.getElementById('editLeaveModal'));
            editModal.show();
        }, 400);
    };

    window.deleteRecordFromHistory = function(id) {
        // Close View Modal
        const viewModalEl = document.getElementById('viewLeaveModal');
        const viewModal = bootstrap.Modal.getInstance(viewModalEl);
        if (viewModal) viewModal.hide();

        // Use existing delete confirmation logic
        document.getElementById('confirmDeleteCount').textContent = '1';
        
        // Temporarily store the single ID to delete
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const originalListener = confirmDeleteBtn.onclick;
        
        confirmDeleteBtn.onclick = function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';

            const formData = new FormData();
            formData.append('ids[]', id);

            fetch('/sdo-leave-monitoring/actions/delete-leaves.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Delete';
                }
            });
        };

        const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        confirmModal.show();

        // Cleanup: restore original listener when modal closes
        document.getElementById('confirmDeleteModal').addEventListener('hidden.bs.modal', function () {
            confirmDeleteBtn.onclick = null; // Or restore original if needed
        }, { once: true });
    };

    function populateEditModal(data, mode = 'employee') {
        document.getElementById('editLeaveId').value = data.id || data.dataset?.id || '';
        document.getElementById('editEmployeeId').value = data.employee_id || data.dataset?.employeeId || '';
        document.getElementById('editEmpNo').value = data.employee_no || data.dataset?.employeeno || '';
        document.getElementById('editSurname').value = data.surname || data.dataset?.surname || '';
        document.getElementById('editFirstName').value = data.first_name || data.dataset?.firstname || '';
        document.getElementById('editMi').value = data.middle_initial || data.dataset?.mi || '';
        
        const dobVal = data.dob || data.dataset?.dob || '';
        document.getElementById('editDob').value = (dobVal !== '0000-00-00' && dobVal !== 'N/A') ? dobVal : '';
        document.getElementById('editPob').value = data.pob || data.dataset?.pob || '';
        document.getElementById('editLeaveFrom').value = data.period_from || data.dataset?.periodfrom || '';
        document.getElementById('editLeaveTo').value = data.period_to || data.dataset?.periodto || '';
        document.getElementById('editStation').value = data.station || data.dataset?.station || '';
        document.getElementById('editTotalDays').value = data.total_days || data.dataset?.totaldays || '';
        document.getElementById('editRemarks').value = data.remarks || data.dataset?.remarks || '';
        
        if (document.getElementById('editSchoolLevel')) {
            document.getElementById('editSchoolLevel').value = data.school_level || data.dataset?.schoollevel || 'ES';
        }
        
        if (document.getElementById('editStatus')) {
            document.getElementById('editStatus').value = data.status || data.dataset?.status || 'Active';
        }
        
        const reasonSelect = document.getElementById('editLeaveReason');
        const otherReasonInput = document.getElementById('editOtherReason');
        const reason = data.reason || data.dataset?.reason || '';
        
        let reasonFound = false;
        Array.from(reasonSelect.options).forEach(opt => {
            if (opt.value === reason) reasonFound = true;
        });
        
        if (reasonFound) {
            reasonSelect.value = reason;
            otherReasonInput.classList.add('d-none');
            otherReasonInput.required = false;
        } else {
            reasonSelect.value = 'Others';
            otherReasonInput.value = reason;
            otherReasonInput.classList.remove('d-none');
            otherReasonInput.required = true;
        }
        
        document.getElementById('editPayStatus').value = data.pay_status || data.dataset?.paystatus || '';

        const employeeSection = document.getElementById('editEmployeeDetailsSection');
        const leaveSection = document.getElementById('editLeaveParticularsSection');
        const title = document.getElementById('editLeaveModalLabel');
        const surname = data.surname || data.dataset?.surname || '';
        const firstName = data.first_name || data.dataset?.firstname || '';

        if (mode === 'leave') {
            // Leave Particulars mode — hide employee details
            if (employeeSection) employeeSection.classList.add('d-none');
            if (leaveSection) leaveSection.classList.remove('d-none');
            if (title) title.innerHTML = `<i class="fas fa-calendar-edit me-2"></i>Edit Leave Particulars: <span class="text-primary">${firstName} ${surname}</span>`;
        } else {
            // Employee Details mode — hide leave particulars
            if (employeeSection) employeeSection.classList.remove('d-none');
            if (leaveSection) leaveSection.classList.add('d-none');
            if (title) title.innerHTML = `<i class="fas fa-user-edit me-2"></i>Edit Employee Details: <span class="text-primary">${firstName} ${surname}</span>`;
        }
    }

    // Edit Employee Details (Demographics)
    const editEmpDetailsButtons = document.querySelectorAll('.edit-employee-details-btn');
    editEmpDetailsButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            populateEditModal({dataset: this.dataset}, 'employee');
            const editModal = new bootstrap.Modal(document.getElementById('editLeaveModal'));
            editModal.show();
        });
    });

    // Edit Leave Record (Particulars)
    const editLeaveButtons = document.querySelectorAll('.edit-btn');
    editLeaveButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            populateEditModal({dataset: this.dataset}, 'leave');
            const editModal = new bootstrap.Modal(document.getElementById('editLeaveModal'));
            editModal.show();
        });
    });

    // Edit Modal 'Others' toggle
    const editReasonSelect = document.getElementById('editLeaveReason');
    const editOtherInput = document.getElementById('editOtherReason');
    
    editReasonSelect.addEventListener('change', function() {
        const payStatus = document.getElementById('editPayStatus');
        
        if (this.value === 'Others') {
            editOtherInput.classList.remove('d-none');
            editOtherInput.required = true;
        } else {
            editOtherInput.classList.add('d-none');
            editOtherInput.required = false;
        }

        const withoutPayReasons = ['Sick leave without pay', 'Vacation leave without pay'];
        const withPayReasons = ['Sick leave with pay', 'Vacation leave with pay'];

        if (withoutPayReasons.includes(this.value)) {
            payStatus.value = 'Without Pay';
        } else if (withPayReasons.includes(this.value)) {
            payStatus.value = 'With Pay';
        }
    });

    // Edit Modal Days Calculator
    const editFromDate = document.getElementById('editLeaveFrom');
    const editToDate = document.getElementById('editLeaveTo');
    const editTotalDays = document.getElementById('editTotalDays');

    function calculateEditDays() {
        if (editFromDate.value && editToDate.value) {
            const start = new Date(editFromDate.value);
            const end = new Date(editToDate.value);
            start.setHours(0,0,0,0);
            end.setHours(0,0,0,0);
            
            if (end >= start) {
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                editTotalDays.value = diffDays;
            } else {
                editTotalDays.value = 'Invalid';
            }
        } else {
            editTotalDays.value = '0';
        }
    }

    editFromDate.addEventListener('change', calculateEditDays);
    editToDate.addEventListener('change', calculateEditDays);

    // AJAX Update Submit
    document.getElementById('editLeaveForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        const btn = form.querySelector('button[type="submit"]');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';

        fetch('../actions/update-leave.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const alertContainer = document.getElementById('alertContainer');
            if (data.status === 'success') {
                alertContainer.innerHTML = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                const modal = bootstrap.Modal.getInstance(document.getElementById('editLeaveModal'));
                modal.hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                alertContainer.innerHTML = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
        })
        .catch(error => {
            console.error('Update Error:', error);
            document.getElementById('alertContainer').innerHTML = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>An error occurred while updating: ' + error.message + '. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Update Record';
        });
    });
    
    // --- BULK DELETION LOGIC ---
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const selectedCountSpan = document.getElementById('selectedCount');
    const toggleDeleteMode = document.getElementById('toggleDeleteMode');

    function updateDeleteButton() {
        const selected = document.querySelectorAll('.row-checkbox:checked');
        const count = selected.length;
        selectedCountSpan.textContent = count;
        
        const deleteModeActive = !document.querySelector('.checkbox-col')?.classList.contains('d-none');
        
        if (count > 0 && deleteModeActive) {
            deleteSelectedBtn.classList.remove('d-none');
        } else {
            deleteSelectedBtn.classList.add('d-none');
        }
    }

    if (toggleDeleteMode) {
        toggleDeleteMode.addEventListener('click', function() {
            const checkboxCols = document.querySelectorAll('.checkbox-col');
            const isActive = !checkboxCols[0].classList.contains('d-none');
            
            if (isActive) {
                // Hiding: Reset state
                checkboxCols.forEach(col => col.classList.add('d-none'));
                if (selectAll) selectAll.checked = false;
                rowCheckboxes.forEach(cb => cb.checked = false);
                updateDeleteButton();
                this.classList.remove('btn-secondary');
                this.classList.add('btn-danger');
                this.innerHTML = '<i class="fas fa-trash-alt me-2"></i>Delete';
            } else {
                // Showing:
                checkboxCols.forEach(col => col.classList.remove('d-none'));
                this.classList.remove('btn-danger');
                this.classList.add('btn-secondary');
                this.innerHTML = '<i class="fas fa-times me-2"></i>Cancel Delete';
            }
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateDeleteButton();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateDeleteButton);
    });

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function() {
            const selected = document.querySelectorAll('.row-checkbox:checked');
            const count = selected.length;
            
            document.getElementById('confirmDeleteCount').textContent = count;
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
        });
    }

    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            const selected = document.querySelectorAll('.row-checkbox:checked');
            const ids = Array.from(selected).map(cb => cb.value);
            const btn = this;
            const originalHTML = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';

            const formData = new FormData();
            ids.forEach(id => formData.append('ids[]', id));

            fetch('../actions/delete-leaves.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                const alertContainer = document.getElementById('alertContainer');
                if (data.status === 'success') {
                    alertContainer.innerHTML = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                    modal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alertContainer.innerHTML = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                document.getElementById('alertContainer').innerHTML = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>An error occurred. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            });
        });
    }
    // --- MULTI-FILE EXCEL UPLOAD LOGIC ---
    const excelFileInput = document.getElementById('excelFileInput');
    const fileUploadList = document.getElementById('fileUploadList');
    const fileListGroup = fileUploadList?.querySelector('.list-group');
    // Store extracted data to send to server
    let extractedMetadataMap = {};

    if (excelFileInput) {
        excelFileInput.addEventListener('change', async function() {
            const previewBody = document.getElementById('previewTableBody');
            if (!previewBody) return;

            if (this.files.length > 0) {
                fileUploadList.classList.remove('d-none');
                
                const uploadStatus = document.getElementById('uploadStatusSelect').value;
                const getBadgeClass = (s) => (s === 'Inactivation' ? 'bg-warning text-dark' : (s === 'Separation' ? 'bg-danger' : 'bg-success'));
                const statusBadge = `<span class="badge ${getBadgeClass(uploadStatus)} p-1" style="font-size: 0.65rem;">${uploadStatus}</span>`;

                previewBody.innerHTML = ''; 
                
                const files = Array.from(this.files);
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    let empNo = "N/A", surname = "N/A", first = "N/A", mi = "", dobPob = "N/A";

                    try {
                        const dataBuffer = await readFileAsArrayBuffer(file);
                        const workbook = XLSX.read(dataBuffer, { type: 'array' });
                        const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                        const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                        const empNoKeywords = ['EMPLOYEE NO', 'EMP NO', 'ID NO', 'NO.:'];
                        
                        let bestName = { surname: "N/A", first: "N/A", mi: "", score: -1 };
                        let bestEmpNo = { val: "N/A", score: -1 };
                        let bestBirth = { val: "N/A", score: -1 };
                        
                        const ILLEGAL_NAME_PARTS = /EXECUTIVE|ORDER|DATED|COMPLIANCE|ISSUED|REPUBLIC|PHILIPPINES|OFFICE|DIVISION|SCHOOL|SECTION|PERIOD|LEAVE|FORM|APPLICATION|AUTHORITY|SIGNATURE|POSITION|STATION|DATE|JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER/i;

                        jsonData.forEach((row, rIdx) => {
                            if (!Array.isArray(row)) return;
                            const rowStr = row.join(' ').trim();
                            const rowUpper = rowStr.toUpperCase();
                            if (!rowStr || rowStr.length < 2) return;
                            
                            // 1. Employee No (Clean Digits Only)
                            row.forEach((cell, idx) => {
                                const cs = String(cell || '').trim();
                                if (!cs) return;
                                if (/\d{5,}/.test(cs)) {
                                    let score = 10;
                                    const cu = cs.toUpperCase();
                                    if (rowUpper.includes('NO') || rowUpper.includes('ID') || rowUpper.includes('EMP')) score += 50;
                                    if (cu.includes('EMPLOYEE') || cu.includes('SDO')) score += 20;
                                    
                                    // EXTRACT ONLY DIGITS
                                    const digits = cs.match(/\d+/g);
                                    const cleanVal = digits ? digits.join('') : cs;

                                    if (score > bestEmpNo.score) {
                                        bestEmpNo = { val: cleanVal, score: score };
                                    }
                                }
                            });

                            // 2. Name Detection (Skip birth rows)
                            if (rowUpper.includes('BIRTH') || rowUpper.includes('PLACE') || rowUpper.includes('BORN')) {
                                // SKIP NAME SEARCH IN BIRTH ROWS
                            } else {
                                row.forEach((cell, idx) => {
                                    const cs = String(cell || '').trim();
                                    if (cs.length < 2) return;
                                    const cu = cs.toUpperCase();
                                    
                                    if (cu === "NAME" || cu === "NAME:" || cu === "SURNAME" || cu === "FIRST" || cu === "MIDDLE") return;
                                    if (ILLEGAL_NAME_PARTS.test(cu) && !cu.includes(',')) return;

                                    let currentScore = 0;
                                    let candSurname = "", candFirst = "N/A", candMi = "";

                                    if (cu.includes(',')) {
                                        const p = cs.split(',');
                                        candSurname = p[0].trim();
                                        const fPart = (p[1] || '').trim();
                                        const fp = fPart.split(' ');
                                        candFirst = fp[0];
                                        candMi = fp.length > 1 ? fp[fp.length-1].replace('.', '') : "";
                                        currentScore += 100;
                                    } else {
                                        let isNearLabel = false;
                                        if (String(row[idx-1] || '').toUpperCase().includes('NAME')) isNearLabel = true;
                                        if (String(row[idx-1] || '').toUpperCase().includes('SURNAME')) isNearLabel = true;
                                        
                                        if (isNearLabel || (rIdx > 1 && rIdx < 15 && idx < 5)) {
                                            candSurname = cs;
                                            for (let o = 1; o <= 5; o++) {
                                                let v = String(row[idx+o] || '').trim();
                                                let vu = v.toUpperCase();
                                                if (v && v.length > 1 && !ILLEGAL_NAME_PARTS.test(vu) && !vu.includes('NAME') && !vu.includes('FIRST')) {
                                                    candFirst = v;
                                                    for (let mo = 1; mo <= 3; mo++) {
                                                        let mv = String(row[idx+o+mo] || '').trim();
                                                        if (mv && mv.length >= 1 && mv.length <= 3) {
                                                            candMi = mv.replace('.', '');
                                                            break;
                                                        }
                                                    }
                                                    break;
                                                }
                                            }
                                            currentScore += isNearLabel ? 80 : 30;
                                        }
                                    }

                                    if (ILLEGAL_NAME_PARTS.test(cu)) currentScore -= 1000;
                                    if (cu.includes('(') || cu.includes('CHECKED') || cu.includes('BASIS') || cu.includes('BORN') || cu.includes('BIRTH')) currentScore -= 2000;
                                    
                                    if (currentScore > bestName.score && candSurname.length > 1) {
                                        bestName = { surname: candSurname, first: candFirst, mi: candMi, score: currentScore };
                                    }
                                });
                            }

                            // 3. Birth Detection (Improved filtering)
                            if (rowUpper.includes('BIRTH') || rowUpper.includes('BORN')) {
                                row.forEach((cell, idx) => {
                                    if (String(cell || '').toUpperCase().includes('BIRTH')) {
                                        let dStr = "N/A", pStr = "N/A";
                                        for (let o = 1; o <= 10; o++) {
                                            let v = row[idx + o];
                                            if (typeof v === 'number' && v > 20000) {
                                                dStr = new Date((v - 25569) * 86400 * 1000).toLocaleDateString();
                                            } else if (v && String(v).length > 5 && !isNaN(new Date(v)) && !String(v).includes('_')) {
                                                dStr = new Date(v).toLocaleDateString();
                                            }
                                            if (dStr !== "N/A") break;
                                        }
                                        for (let o = 1; o <= 30; o++) {
                                            let v = String(row[idx + o] || '').trim();
                                            const vu = v.toUpperCase();
                                            if (v.length > 2 && !ILLEGAL_NAME_PARTS.test(vu) && v !== dStr && !/^\d+$/.test(v) && !v.includes('(') && !v.includes('/') && !vu.includes('BASIS') && !vu.includes('CHECKED')) {
                                                pStr = v;
                                                break;
                                            }
                                        }
                                        if (dStr !== 'N/A' || pStr !== 'N/A') {
                                            let score = (dStr !== 'N/A' ? 20 : 0) + (pStr !== 'N/A' ? 20 : 0);
                                            if (score > bestBirth.score) {
                                                bestBirth = { val: dStr + ' / ' + pStr, score: score };
                                            }
                                        }
                                    }
                                });
                            }
                        });

                        // FINAL ASSIGNMENT
                        empNo = bestEmpNo.val;
                        surname = bestName.surname;
                        first = bestName.first;
                        mi = bestName.mi;
                        dobPob = bestBirth.val;

                         // Store for upload
                        extractedMetadataMap[file.name] = {
                            employee_no: empNo,
                            surname: surname,
                            first_name: first,
                            mi: mi,
                            dob_pob: dobPob
                        };

                        const tr = document.createElement('tr');
                        tr.id = `file-item-${i}`;
                        tr.innerHTML = `
                            <td>${i + 1}</td>
                            <td class="small">${empNo}</td>
                            <td>${surname}</td>
                            <td>${first}</td>
                            <td>${mi}</td>
                            <td class="small text-truncate" style="max-width: 140px;" title="${dobPob}">${dobPob}</td>
                            <td class="text-center">${statusBadge}</td>
                            <td class="status-icon text-center"><i class="far fa-clock text-secondary"></i></td>
                        `;
                        previewBody.appendChild(tr);
                    } catch (err) {
                        console.error("Error parsing file:", file.name, err);
                        const tr = document.createElement('tr');
                        tr.id = `file-item-${i}`;
                        tr.innerHTML = `<td colspan="6" class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i> ${file.name}: Error reading</td><td class="text-center">${statusBadge}</td><td class="status-icon text-center"><i class="fas fa-times text-danger"></i></td>`;
                        previewBody.appendChild(tr);
                    }
                }
            } else {
                fileUploadList.classList.add('d-none');
            }
        });
        // Dynamically update preview badges if status dropdown changes
        const statusSelect = document.getElementById('uploadStatusSelect');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                const newStatus = this.value;
                const getBadgeClass = (s) => (s === 'Inactivation' ? 'bg-warning text-dark' : (s === 'Separation' ? 'bg-danger' : 'bg-success'));
                const badges = document.querySelectorAll('#previewTableBody .badge');
                badges.forEach(b => {
                    b.className = `badge ${getBadgeClass(newStatus)} p-1`;
                    b.textContent = newStatus;
                });
            });
        }

        // --- NEW: Clear Selection Button ---
        const clearUploadBtn = document.getElementById('clearUploadBtn');
        if (clearUploadBtn) {
            clearUploadBtn.addEventListener('click', function() {
                if (excelFileInput) excelFileInput.value = '';
                if (fileUploadList) fileUploadList.classList.add('d-none');
                const previewBody = document.getElementById('previewTableBody');
                if (previewBody) previewBody.innerHTML = '';
                extractedMetadataMap = {};
            });
        }
    }

    const uploadExcelForm = document.getElementById('uploadExcelForm');
    if (uploadExcelForm) {
        uploadExcelForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('uploadBtn');
            const originalHTML = btn.innerHTML;
            const fileInput = document.getElementById('excelFileInput');
            const statusSelect = document.getElementById('uploadStatusSelect');
            const levelSelect = document.getElementById('uploadLevelSelect');
            const files = Array.from(fileInput.files);
            const uploadStatus = statusSelect ? statusSelect.value : 'Active';
            const uploadLevel = levelSelect ? levelSelect.value : 'ES';
            
            if (files.length === 0) return;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing Files...';

            let totalSuccess = 0;
            let totalErrors = 0;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const itemIcon = document.querySelector(`#file-item-${i} .status-icon`);
                
                // Update to loading
                if (itemIcon) itemIcon.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';

                // Small delay to prevent server overload
                await new Promise(r => setTimeout(r, 200));

                try {
                    const data = await readFileAsArrayBuffer(file);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                    
                    let records = jsonData.filter(row => row.length > 0);
                    // Skip header if it looks like one
                    if (records[0] && String(records[0][0]).toLowerCase().includes('employee')) {
                        records = records.slice(1);
                    }

                    const metadata = extractedMetadataMap[file.name] || {};

                    const response = await fetch('../actions/upload-leaves.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            records: records,
                            filename: file.name,
                            metadata: metadata,
                            upload_status: uploadStatus,
                            upload_level: uploadLevel
                        })
                    });

                    let result;
                    const responseText = await response.text();
                    
                    if (!response.ok) {
                        throw new Error(`Server Error: ${response.status} ${response.statusText}`);
                    }

                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        console.error('PHP Output:', responseText);
                        // Show a snippet of the raw output in the error
                        const snippet = responseText.substring(0, 50).replace(/<[^>]*>?/gm, '');
                        throw new Error('Invalid Response: ' + snippet);
                    }

                    if (result.status === 'success') {
                        if (itemIcon) itemIcon.innerHTML = '<i class="fas fa-check-circle text-success" title="' + result.message + '"></i>';
                        totalSuccess++;
                    } else {
                        throw new Error(result.message || 'Upload failed');
                    }
                } catch (err) {
                    console.error(`Error uploading ${file.name}:`, err);
                    if (itemIcon) itemIcon.innerHTML = `<i class="fas fa-times-circle text-danger" title="${err.message}"></i>`;
                    totalErrors++;
                }
            }

            const alertContainer = document.getElementById('alertContainer');
            if (totalSuccess > 0) {
                alertContainer.innerHTML = `<div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <b>Upload Finished!</b> Successfully processed ${totalSuccess} file(s). ${totalErrors > 0 ? totalErrors + ' failed.' : ''}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                alertContainer.innerHTML = `<div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <b>Upload Failed!</b> All ${files.length} file(s) failed to upload.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        });
    }

    // Helper to read file as ArrayBuffer using Promise
    function readFileAsArrayBuffer(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(new Uint8Array(e.target.result));
            reader.onerror = (e) => reject(new Error('File reading failed'));
            reader.readAsArrayBuffer(file);
        });
    }

    // --- IMPORT SINGLE RECORD FROM EXCEL INTO ADD MODAL ---
    const importBtn = document.getElementById('importFromExcelBtn');
    const importInput = document.getElementById('importFromExcelInput');

    if (importBtn && importInput) {
        importBtn.addEventListener('click', () => importInput.click());

        importInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                let surname = "", firstName = "", mi = "", dob = "", pob = "";
                let from = "", to = "", reason = "", station = "", pay = "", remarks = "";

                // Fuzzy Search for Name/Details in the sheet
                jsonData.forEach(row => {
                    const rowStr = row.join(' ').toUpperCase();
                    if (rowStr.includes('NAME:')) {
                        row.forEach((cell, idx) => {
                            let val = String(cell).toUpperCase();
                            if (val.includes('NAME:')) {
                                let nameVal = val.replace('NAME:', '').trim();
                                if (!nameVal && row[idx+1]) nameVal = String(row[idx+1]);
                                if (!nameVal && row[idx+4]) nameVal = String(row[idx+4]);
                                if (nameVal) {
                                    if (nameVal.includes(',')) {
                                        const parts = nameVal.split(',');
                                        surname = parts[0].trim();
                                        firstName = (parts[1] || '').trim();
                                    } else {
                                        surname = nameVal.trim();
                                    }
                                }
                            }
                        });
                    }
                    if (rowStr.includes('BIRTH:')) {
                        row.forEach((cell, idx) => {
                            if (String(cell).toUpperCase().includes('BIRTH:') && row[idx+1]) {
                                let dVal = row[idx+2] || row[idx+1];
                                if (typeof dVal === 'number') {
                                    const d = new Date((dVal - 25569) * 86400 * 1000);
                                    dob = d.toISOString().split('T')[0];
                                } else {
                                    const d = new Date(dVal);
                                    if (!isNaN(d)) dob = d.toISOString().split('T')[0];
                                }
                                pob = String(row[idx+11] || row[idx+10] || row[idx+8] || '').trim();
                            }
                        });
                    }
                    if (!from || !to) {
                        row.forEach((cell, idx) => {
                            if (idx < 5) {
                                let d1 = row[idx], d2 = row[idx+1];
                                if (d1 && d2) {
                                    let date1 = new Date(typeof d1 === 'number' ? (d1 - 25569) * 86400 * 1000 : d1);
                                    let date2 = new Date(typeof d2 === 'number' ? (d2 - 25569) * 86400 * 1000 : d2);
                                    if (!isNaN(date1) && !isNaN(date2) && date1.getFullYear() > 1900 && date2.getFullYear() > 1900) {
                                        from = date1.toISOString().split('T')[0];
                                        to = date2.toISOString().split('T')[0];
                                        reason = String(row[idx+2] || '');
                                        station = String(row[idx+3] || '');
                                        let wp = String(row[idx+5] || '');
                                        pay = (wp && wp != '0') ? 'With Pay' : 'Without Pay';
                                        remarks = String(row[idx+6] || '');
                                    }
                                }
                            }
                        });
                    }
                });

                // Populate form
                const form = document.getElementById('employeeLeaveForm');
                if (surname) form.querySelector('[name="surname"]').value = surname.toUpperCase();
                if (firstName) form.querySelector('[name="first_name"]').value = firstName.toUpperCase();
                if (mi) form.querySelector('[name="middle_initial"]').value = mi.toUpperCase();
                if (dob) form.querySelector('[name="dob"]').value = dob;
                if (pob) form.querySelector('[name="pob"]').value = pob;
                if (from) form.querySelector('[name="period_from"]').value = from;
                if (to) form.querySelector('[name="period_to"]').value = to;
                if (station) form.querySelector('[name="station"]').value = station;
                if (remarks) form.querySelector('[name="remarks"]').value = remarks;
                
                if (reason) {
                   const reasonSelect = form.querySelector('[name="reason"]');
                   let found = false;
                   Array.from(reasonSelect.options).forEach(opt => {
                       if (opt.value.toLowerCase() === reason.toLowerCase()) {
                           reasonSelect.value = opt.value;
                           found = true;
                       }
                   });
                   if (!found && reason) {
                       reasonSelect.value = 'Others';
                       const other = document.getElementById('otherReason');
                       other.value = reason;
                       other.classList.remove('d-none');
                       other.required = true;
                   } else {
                       reasonSelect.dispatchEvent(new Event('change'));
                   }
                }

                if (pay) form.querySelector('[name="pay_status"]').value = pay;
                
                const fromInput = document.getElementById('leaveFrom');
                fromInput.dispatchEvent(new Event('change'));

                alert('Data successfully loaded from Excel. Please review and click Save.');
                importInput.value = '';
            };
            reader.readAsArrayBuffer(file);
        });
    }

    // Reset Modal on Close
    const addModalEl = document.getElementById('addEmployeeLeaveModal');
    if (addModalEl) {
        addModalEl.addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('employeeLeaveForm');
            const leaveSection = document.getElementById('leaveParticularsSection');
            const employeeSection = document.getElementById('employeeDetailsSection');
            const btn = document.getElementById('saveLeaveBtn');
            const title = document.getElementById('addEmployeeLeaveModalLabel');
            
            form.reset();
            if (leaveSection) leaveSection.classList.add('d-none');
            if (employeeSection) employeeSection.classList.remove('d-none');
            if (btn) btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Record';
            if (title) title.innerHTML = '<i class="fas fa-user-plus me-2"></i>Add Employee';
            document.getElementById('newEmployeeId').value = '';
        });
    }

    // Ensure "Add Employee" button shows employee details
    const addEmployeeBtn = document.querySelector('[data-bs-target="#addEmployeeLeaveModal"]');
    if (addEmployeeBtn) {
        addEmployeeBtn.addEventListener('click', function() {
            const leaveSection = document.getElementById('leaveParticularsSection');
            const employeeSection = document.getElementById('employeeDetailsSection');
            const title = document.getElementById('addEmployeeLeaveModalLabel');
            const saveBtn = document.getElementById('saveLeaveBtn');
            
            if (leaveSection) leaveSection.classList.add('d-none');
            if (employeeSection) employeeSection.classList.remove('d-none');
            if (title) title.innerHTML = '<i class="fas fa-user-plus me-2"></i>Add Employee';
            if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Record';
            document.getElementById('newEmployeeId').value = '';
        });
    }
    window.openAddLeaveFromHistory = function(surname, firstname, mi, employeeId, dob, pob, empNo) {
        // Populate Add Leave Modal
        const form = document.getElementById('employeeLeaveForm');
        document.getElementById('newEmployeeId').value = employeeId;
        form.querySelector('[name="surname"]').value = surname;
        form.querySelector('[name="first_name"]').value = firstname;
        form.querySelector('[name="middle_initial"]').value = mi;
        form.querySelector('[name="dob"]').value = (dob && dob !== '0000-00-00') ? dob : '';
        form.querySelector('[name="pob"]').value = pob;
        form.querySelector('[name="employee_no"]').value = empNo;

        // Show leave particulars section, hide employee details
        const leaveSection = document.getElementById('leaveParticularsSection');
        const employeeSection = document.getElementById('employeeDetailsSection');
        if (leaveSection) leaveSection.classList.remove('d-none');
        if (employeeSection) employeeSection.classList.add('d-none');
        
        // Update Modal Title
        const fullName = `${firstname} ${surname}`;
        const title = document.getElementById('addEmployeeLeaveModalLabel');
        if (title) title.innerHTML = `<i class="fas fa-plus-circle me-2"></i> Add Leave: ${fullName}`;
        
        // Update Save Button
        const saveBtn = document.getElementById('saveLeaveBtn');
        if (saveBtn) saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Leave Record';

        // Show Modal
        const addModal = new bootstrap.Modal(document.getElementById('addEmployeeLeaveModal'));
        addModal.show();
    };
});
