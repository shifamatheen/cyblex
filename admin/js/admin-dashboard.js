document.addEventListener('DOMContentLoaded', function() {
    // Load pending verifications when the page loads
    loadPendingVerifications();
});

async function loadPendingVerifications() {
    try {
        const response = await fetch('../api/get-pending-verifications.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        // Debug logging
        console.log('Raw response:', response);
        console.log('Response data:', data);
        console.log('Data type:', typeof data);
        console.log('Is array?', Array.isArray(data));
        if (data.verifications) {
            console.log('Verifications type:', typeof data.verifications);
            console.log('Is verifications array?', Array.isArray(data.verifications));
        }
        
        const tableBody = document.getElementById('verificationsTableBody');
        if (!tableBody) {
            console.error('Table body element not found');
            return;
        }
        tableBody.innerHTML = '';
        
        // Handle different response formats
        let verifications = [];
        if (Array.isArray(data)) {
            verifications = data;
        } else if (data && typeof data === 'object') {
            if (data.verifications && Array.isArray(data.verifications)) {
                verifications = data.verifications;
            } else if (data.success && data.verifications) {
                verifications = Array.isArray(data.verifications) ? data.verifications : [];
            }
        }
        
        console.log('Processed verifications:', verifications);
        
        if (!verifications || verifications.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center">No pending verifications found</td>
                </tr>
            `;
            return;
        }
        
        // Now we can safely use forEach on verifications array
        verifications.forEach(verification => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${verification.full_name || ''}</td>
                <td>${verification.email || ''}</td>
                <td>${verification.specialization || ''}</td>
                <td>${verification.submitted_at ? new Date(verification.submitted_at).toLocaleDateString() : ''}</td>
                <td>
                    <button class="btn btn-success btn-sm" onclick="handleVerification(${verification.lawyer_id}, 'approve')">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="handleVerification(${verification.lawyer_id}, 'reject')">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading verifications:', error);
        const tableBody = document.getElementById('verificationsTableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger">
                        Error loading verifications: ${error.message}
                    </td>
                </tr>
            `;
        }
    }
}

async function handleVerification(lawyerId, action) {
    try {
        const response = await fetch('../api/verify-advisor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lawyer_id: lawyerId,
                action: action
            })
        });

        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to process verification');
        }

        // Show success message
        alert(data.message || 'Verification processed successfully');
        
        // Reload the verifications list
        loadPendingVerifications();
    } catch (error) {
        console.error('Error handling verification:', error);
        alert('Error: ' + error.message);
    }
} 