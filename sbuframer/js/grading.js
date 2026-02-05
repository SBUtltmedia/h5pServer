function postLTI(ses, name) {
    console.log("--- GRADING.JS: ATTEMPTING TO FETCH postLTI.php NOW ---");

    // Helper to convert a nested object to the x-www-form-urlencoded format that PHP expects.
    // This mimics jQuery's $.post behavior for objects.
    function buildFormData(data, parentKey) {
        let formData = [];
        for (let key in data) {
            if (data.hasOwnProperty(key)) {
                let fullKey = parentKey ? `${parentKey}[${key}]` : key;
                if (typeof data[key] === 'object' && data[key] !== null) {
                    formData.push(buildFormData(data[key], fullKey));
                } else {
                    formData.push(`${encodeURIComponent(fullKey)}=${encodeURIComponent(data[key])}`);
                }
            }
        }
        return formData.join('&');
    }

    const url = `/LTI/postLTI.php?name=${encodeURIComponent(name || '')}`;
    // The 'ses' object is wrapped under a 'data' key to create the 'data[...]' parameter structure.
    const body = buildFormData(ses, 'data');

    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: body
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .catch(error => {
        console.error('Error in postLTI:', error);
        throw error;
    });
}
