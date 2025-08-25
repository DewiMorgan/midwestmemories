/* jshint esversion: 6 */
window.ImageTypes = class {
    /**
     * Initialize the image type selector dropdown.
     * @param {string} dropdownId - The ID of the select element.
     * @param {string} currentType - The selected image type.
     */
    static init(dropdownId, currentType) {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) {
            console.error(`Image type dropdown with ID '${dropdownId}' not found.`);
            return;
        }

        // Store the current image element
        this.imageElement = document.querySelector('img.file');
        if (!this.imageElement) {
            console.error('Could not find image element with class "file"');
            return;
        }

        // Store the original image URL
        this.originalImageUrl = this.imageElement.src;

        // Set the current value
        if (currentType) {
            dropdown.value = currentType;
            // Ensure the current image URL matches the selected type
            this.updateImageUrl(currentType);
        }

        // Add change event listener
        dropdown.addEventListener('change', (event) => {
            const newType = event.target.value;
            this.updateImageUrl(newType);
            this.saveImageType(newType);
        });
    }

    /**
     * Update the image URL based on the selected type.
     * @param {string} imageType - The selected image type.
     */
    static updateImageUrl(imageType) {
        if (!this.imageElement) {
            return;
        }

        const url = new URL(this.imageElement.src);
        const pathParts = url.pathname.split('/');
        let filename = pathParts.pop();

        // Remove any existing prefixes/suffixes to get the base filename
        filename = filename.replace(/|-(?:ICE|WEB|TN|B)(?=\.[^.]+)$/, '');

        // Apply the appropriate transformation based on the image type
        switch (imageType) {
            case 'back':
                filename = filename.replace(/\.[^.]+$/, '-B.jpg');
                break;
            case 'web':
                filename = filename.replace(/\.[^.]+$/, '-WEB.jpg');
                break;
            case 'thumbnail':
                filename = filename.replace(/\.[^.]+$/, '-TN.jpg');
                break;
            case 'ice':
                filename = filename.replace(/\.[^.]+$/, '-ICE.jpg');
                break;
            case 'original':
                filename = filename.replace(/\.[^.]+$/, '.jpg');
        }

        // Reconstruct the URL with the new filename
        pathParts.push(filename);
        url.pathname = pathParts.join('/');

        // Update the image source
        this.imageElement.src = url.toString();
    }

    /**
     * Save the selected image type via the API.
     * @param {string} imageType - The image type to save.
     */
    static async saveImageType(imageType) {
        try {
            await Api.fetchApiData(
                '/api/v1.0/image_type',
                'POST',
                'string',
                {image_type: imageType}
            );
            console.log(`Successfully updated image type to: ${imageType}`);
        } catch (error) {
            console.error('Failed to update image type:', error);
            // Revert the image URL on error
            if (this.imageElement && this.originalImageUrl) {
                this.imageElement.src = this.originalImageUrl;
            }
            // Show error to user
            alert(`Failed to update image type: ${error.message}`);
        }
    }
};
