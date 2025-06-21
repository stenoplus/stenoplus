<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steno Plus - Upload Audio & Transcription</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex flex-col items-center min-h-screen">

    <!-- Header -->
    <header class="w-full bg-[#002147] text-white text-center py-4 text-xl font-bold">
        Steno Plus - Audio & Transcription Upload
    </header>

    <!-- Upload Form -->
    <div class="bg-white shadow-lg rounded-lg p-6 mt-6 w-full max-w-lg">
        <h2 class="text-[#002147] text-lg font-bold mb-4">Upload Your Files</h2>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="text" id="title" name="title" placeholder="Enter Title" 
                   class="w-full p-2 border rounded mb-2" required>

            <select id="category" name="category" class="w-full p-2 border rounded mb-2" required>
                <option value="">Select Category</option>
                <!-- Categories will be loaded dynamically -->
            </select>

            <label class="block text-gray-600 mt-2">Upload Audio File:</label>
            <input type="file" id="audioFile" name="audioFile" accept="audio/*" 
                   class="w-full p-2 border rounded mb-2" required>

            <label class="block text-gray-600 mt-2">Upload Transcription File:</label>
            <input type="file" id="transcriptionFile" name="transcriptionFile" accept=".txt,.doc,.pdf" 
                   class="w-full p-2 border rounded mb-2">

            <button type="submit" class="bg-[#D2171E] text-white px-4 py-2 rounded w-full mt-2">
                Upload
            </button>
        </form>

        <p id="message" class="mt-2 text-center text-green-500"></p>
    </div>

    <script>
    async function loadCategories() {
        const response = await fetch("get-categories.php");
        const categories = await response.json();
        
        let categorySelect = document.getElementById("category");
        categories.forEach(cat => {
            let option = document.createElement("option");
            option.value = cat.id;
            option.innerText = cat.name;
            categorySelect.appendChild(option);
        });
    }

    loadCategories();

    document.getElementById("uploadForm").addEventListener("submit", async function (event) {
        event.preventDefault();
        let formData = new FormData(this);

        const response = await fetch("upload.php", { method: "POST", body: formData });
        const result = await response.json();

        if (result.success) {
            document.getElementById("message").innerText = "Upload Successful!";
        } else {
            document.getElementById("message").innerText = "Upload Failed! " + result.error;
        }
    });
    </script>

</body>
</html>
