// Function to create tutorial images
function createTutorialImages() {
    console.log('Attempting to create tutorial images...');
    
    // First try to create the directory
    $.ajax({
        url: '/NS/api/system/create-tutorial-directory.php',
        type: 'GET',
        dataType: 'json',
        success: function(dirResponse) {
            console.log('Directory creation response:', dirResponse);
            
            if (dirResponse.success) {
                // Now try to create the images
                $.ajax({
                    url: '/NS/api/system/create-tutorial-images.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Image creation response:', response);
                        
                        if (response.success) {
                            console.log('Tutorial images created successfully');
                            // Force reload images with cache busting
                            $('.carousel-item img').each(function() {
                                const src = $(this).attr('src');
                                $(this).attr('src', src + '?v=' + new Date().getTime());
                            });
                        } else {
                            console.error('Failed to create tutorial images:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error creating tutorial images:', error);
                    }
                });
            } else {
                console.error('Failed to create tutorial directory:', dirResponse.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error creating tutorial directory:', error);
        }
    });
}

// Create tutorial images when the document is ready
$(document).ready(function() {
    // Check if tutorial images exist, if not create them
    $('.carousel-item img').on('error', function() {
        console.log('Tutorial image not found, creating images...');
        createTutorialImages();
    });
    
    // Also provide a manual way to create images
    $('#create-tutorial-images').on('click', function() {
        createTutorialImages();
    });
});
