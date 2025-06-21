document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("fileInput").addEventListener("change", handleFileUpload);
    document.getElementById("submitButton").addEventListener("click", processText);
    document.getElementById("downloadPDF").addEventListener("click", generatePDF);
});

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById("userInput").value = e.target.result;
    };
    
    reader.readAsText(file);
}

function levenshteinDistance(a, b) {
    let tmp;
    if (a.length === 0) return b.length;
    if (b.length === 0) return a.length;
    if (a.length > b.length) tmp = a, a = b, b = tmp;
    let row = Array(a.length + 1).fill(0).map((_, i) => i);
    for (let i = 1; i <= b.length; i++) {
        let prev = i;
        for (let j = 1; j <= a.length; j++) {
            let val;
            if (b[i - 1] === a[j - 1]) val = row[j - 1];
            else val = Math.min(row[j - 1] + 1, prev + 1, row[j] + 1);
            row[j - 1] = prev;
            prev = val;
        }
        row[a.length] = prev;
    }
    return row[a.length];
}

function processText() {
    let givenText = document.getElementById("givenText").innerText;
    let userInput = document.getElementById("userInput").value;
    let timeTaken = parseInt(document.getElementById("timeTakenInput").value) || 0;
    
    let result = checkMistakes(givenText, userInput);
    
    let typedWords = userInput.split(/\s+/).length;
    let typingSpeed = timeTaken > 0 ? Math.round((typedWords / timeTaken) * 60) : 0;
    
    document.getElementById("fullMistakes").innerText = result.fullMistakes;
    document.getElementById("halfMistakes").innerText = result.halfMistakes;
    document.getElementById("mistakePercent").innerText = result.errorPercentage.toFixed(2) + "%";
    document.getElementById("accuracy").innerText = (100 - result.errorPercentage).toFixed(2) + "%";
    document.getElementById("typingSpeed").innerText = typingSpeed + " WPM";
    document.getElementById("highlightedText").innerHTML = result.highlightedText;
}

function checkMistakes(givenText, userInput) {
    let givenWords = givenText.split(/\s+/);
    let userWords = userInput.split(/\s+/);
    let totalWords = givenWords.length;
    let halfMistakes = 0, fullMistakes = 0;
    let properNouns = ["India", "London", "Google", "John", "January", "Monday"];
    let highlightedText = "";

    for (let i = 0; i < givenWords.length; i++) {
        let original = givenWords[i] || "";
        let typed = userWords[i] || "";

        if (!typed) {
            fullMistakes++;
            highlightedText += `<span class='text-red-500'>${original} </span>`;
            continue;
        }

        if (original !== typed) {
            let originalClean = original.replace(/[.,?!;:'"-]/g, "");
            let typedClean = typed.replace(/[.,?!;:'"-]/g, "");

            if (levenshteinDistance(originalClean, typedClean) === 1) {
                halfMistakes++;
            } else if (typedClean.toLowerCase() === originalClean.toLowerCase()) {
                halfMistakes++;
            } else if (properNouns.includes(original) && typed[0] !== original[0].toUpperCase()) {
                halfMistakes++;
            } else if (typed.length < original.length - 2) {
                fullMistakes++;
            } else {
                fullMistakes++;
            }
            highlightedText += `<span class='text-yellow-500'>${typed} </span>`;
        } else {
            highlightedText += `<span class='text-green-500'>${typed} </span>`;
        }
    }

    let errorPercentage = ((fullMistakes + (halfMistakes * 0.5)) / totalWords) * 100;
    return { fullMistakes, halfMistakes, errorPercentage, highlightedText };
}

function generatePDF() {
    const { jsPDF } = window.jspdf;
    let doc = new jsPDF();
    doc.setFont("helvetica");
    
    doc.text("Dictation Test Results", 10, 10);
    doc.text(`Full Mistakes: ${document.getElementById("fullMistakes").innerText}`, 10, 20);
    doc.text(`Half Mistakes: ${document.getElementById("halfMistakes").innerText}`, 10, 30);
    doc.text(`Mistake Percentage: ${document.getElementById("mistakePercent").innerText}`, 10, 40);
    doc.text(`Accuracy: ${document.getElementById("accuracy").innerText}`, 10, 50);
    doc.text(`Typing Speed: ${document.getElementById("typingSpeed").innerText}`, 10, 60);
    
    doc.save("Dictation_Results.pdf");
}
