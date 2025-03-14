// Global variables
let questions = [];
let currentQuestion = 0;
let answers = {};
let correctAnswers = 0;
let currentFile = '';
let wrongAnswers = {};

// Wrong answers functionality
function loadWrongAnswers() {
  $.getJSON('api/wrong-answers.php', function (response) {
    if (response.success) {
      wrongAnswers = response.data;
      renderWrongAnswers();
    } else {
      console.error('Failed to load wrong answers:', response.error);
      $('#wrongAnswersContainer').html(
        '<div class="col-12"><div class="alert alert-danger">Failed to load wrong answers.</div></div>'
      );
    }
  });
}

function renderWrongAnswers() {
  console.log('Rendering wrong answers:', wrongAnswers);
  let html = '';
  if (Object.keys(wrongAnswers).length === 0) {
    html = '<div class="col-12"><div class="alert alert-info">No wrong answers found.</div></div>';
  } else {
    Object.entries(wrongAnswers).forEach(([fileId, data]) => {
      const examNumber = fileId.match(/\d+/)[0];
      html += `
		                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
		                    <div class="card h-100">
		                        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
		                            <h6 class="mb-0">Exam ${examNumber}</h6>
		                            <span class="badge bg-danger">${data.totalWrong}</span>
		                        </div>
		                        <div class="card-body p-3">
		                            <button class="btn btn-sm btn-primary w-100"
		                                    onclick="showWrongAnswersDetail('${fileId}')"
		                                    data-bs-toggle="modal"
		                                    data-bs-target="#wrongAnswersModal">
		                                Review ${data.totalWrong} Wrong Answer${data.totalWrong > 1 ? 's' : ''}
		                            </button>
		                        </div>
		                    </div>
		                </div>`;
    });
  }
  $('#wrongAnswersContainer').html(html);
}

// Function to show wrong answers detail in modal
function showWrongAnswersDetail(fileId) {
  const data = wrongAnswers[fileId];
  const examNumber = fileId.match(/\d+/)[0];
  $('.modal-title').text(`Practice Exam ${examNumber} - Wrong Answers`);

  let html = '<div class="list-group list-group-flush">';

  data.questions.forEach(q => {
    html += `
		            <div class="list-group-item">
		                <div class="row g-0">
		                    <div class="col-12">
		                        <div class="px-3 py-2">
		                            <div class="question-header">
		                                <div class="question-number">Question ${q.questionNumber}</div>
		                            </div>
		                            <div class="question-text">${q.questionText || 'Question text not available'}</div>
		                            <div class="options">
		                                ${Object.entries(q.options || {}).map(([key, value]) => {
      const isUserAnswer = Array.isArray(q.userAnswer) ? q.userAnswer.includes(key) : q.userAnswer === key;
      const isCorrectAnswer = Array.isArray(q.correctAnswer) ? q.correctAnswer.includes(key) : q.correctAnswer === key;
      return `
		                                        <div class="option small py-1 ${isUserAnswer ? 'user-answer' : ''} ${isCorrectAnswer ? 'correct-answer' : ''}">
		                                            ${key}) ${value}
		                                            ${isUserAnswer || isCorrectAnswer ?
          `<i class="fas fa-${isCorrectAnswer ? 'check text-success' : 'times text-danger'} float-end"></i>`
          : ''}
		                                        </div>`;
    }).join('')}
		                            </div>
		                            <div class="d-flex gap-3 mt-2 small">
		                                <div class="text-danger">
		                                    <small class="text-muted">Your Answer:</small>
		                                    <span class="ms-1">${Array.isArray(q.userAnswer) ? q.userAnswer.join(', ') : q.userAnswer}</span>
		                                </div>
		                                <div class="text-success">
		                                    <small class="text-muted">Correct:</small>
		                                    <span class="ms-1">${Array.isArray(q.correctAnswer) ? q.correctAnswer.join(', ') : q.correctAnswer}</span>
		                                </div>
		                            </div>
		                        </div>
		                    </div>
		                </div>
		            </div>`;
  });

  html += '</div>';
  $('#wrongAnswersDetail').html(html);
}

// Initialize wrong answers count on load
function loadWrongAnswersCount() {
  $.getJSON('api/wrong-answers.php', function (response) {
    if (response.success && response.data) {
      let totalWrong = 0;
      Object.values(response.data).forEach(data => {
        totalWrong += data.totalWrong;
      });
      if (totalWrong > 0) {
        $('#wrongAnswersCount').text(totalWrong);
      }
    }
  });
}

// Initialize when document is ready
$(document).ready(function () {
  // Load initial stats once
  loadAndUpdateHomeStats();

  // Initialize tabs and load initial data
  $('[data-bs-toggle="tab"]').on('click', function (e) {
    e.preventDefault();
    const target = $(this).data('bs-target');

    // Remove active class from all buttons and add to clicked one
    $('[data-bs-toggle="tab"]').removeClass('active');
    $(this).addClass('active');

    // Hide all tab panes and show the target one
    $('.tab-pane').removeClass('show active');
    $(target).addClass('show active');

    // If wrong answers tab is clicked, load the data
    if (target === '#review-tab-pane') {
      loadWrongAnswers();
    }
  });

  // Initial load of wrong answers count
  loadWrongAnswersCount();
});

// Function to load and update home stats
function loadAndUpdateHomeStats() {
  console.log('Loading stats...');
  $.getJSON('api/load-stats.php', function (response) {
    if (response.success) {
      console.log('Loaded home stats:', response.stats);
      if (!response.stats || Object.keys(response.stats).length === 0) {
        console.log('No stats data available');
        return;
      }
      let totalQuestions = 0;
      let totalAnswered = 0;
      let totalCorrect = 0;
      let completedSets = 0;
      const totalSets = Object.keys(response.stats).length;

      // Calculate totals
      console.log('Processing stats data...');
      Object.entries(response.stats).forEach(([fileId, fileStats]) => {
        console.log(`Processing file ${fileId}:`, fileStats);
        totalQuestions += fileStats.total || 0;
        totalAnswered += fileStats.answered || 0;
        totalCorrect += fileStats.correct || 0;

        if (fileStats.answered === fileStats.total && fileStats.total > 0) {
          completedSets++;
        }

        // Update individual card stats
        updateCardStats(fileId, fileStats);
      });

      console.log('Calculated totals:', {
        totalQuestions,
        totalAnswered,
        totalCorrect,
        completedSets
      });

      // Update overall progress
      const overallProgress = totalQuestions > 0 ? ((totalAnswered / totalQuestions) * 100).toFixed(1) : 0;
      const overallSuccess = totalAnswered > 0 ? ((totalCorrect / totalAnswered) * 100).toFixed(1) : 0;

      // Update UI elements
      $('#overallProgress').text(overallProgress);
      $('#totalAnswered').text(totalAnswered);
      $('#totalQuestions').text(totalQuestions);
      $('#overallSuccess').text(overallSuccess);
      $('#totalCorrect').text(totalCorrect);
      $('#completedSets').text(completedSets);
      $('#totalSets').text(totalSets);

      console.log('Home stats updated successfully');
    } else {
      console.error('Failed to load home stats:', response.error);
    }
  });
}

function showQuestionSets() {
  $('#examContainer').hide();
  $('#questionSetsContainer').show();
  $('#examInfo').text('');
  // Reset current exam state
  questions = [];
  currentQuestion = 0;
  answers = {};
  correctAnswers = 0;
  currentFile = '';

  // Load fresh stats when returning to question sets view
  loadAndUpdateHomeStats();
}

function loadQuestionSet(filename) {
  console.log('Loading question set:', filename);
  if (!filename) {
    console.error('No filename provided');
    return;
  }
  currentFile = filename;
  answers = {};
  correctAnswers = 0;
  currentQuestion = 0;

  $('#questionSetsContainer').hide();
  $('#examContainer').show();

  loadQuestions();
}

function loadQuestions() {
  console.log('Loading questions for file:', currentFile);
  $.getJSON('api/questions.php', {
    file: currentFile
  }, function (data) {
    console.log('API response:', data);
    if (data.success) {
      questions = data.data;
      // Load saved answers
      $.getJSON('api/load-progress.php', {
        file: currentFile
      }, function (progressData) {
        if (progressData.success && progressData.data) {
          answers = progressData.data.answers;
          correctAnswers = Object.values(answers).filter(a => a.correct).length;
          updateProgress();

          // Find the first unanswered question
          currentQuestion = findFirstUnansweredQuestion();
        }
        $('#totalQuestions').text(questions.length);
        $('#examInfo').text('File: ' + currentFile);
        showQuestion(currentQuestion);

        // Set up navigation button handlers
        $('#prevBtn').off('click').on('click', function () {
          if (currentQuestion > 0) {
            currentQuestion--;
            showQuestion(currentQuestion);
          }
        });

        $('#nextBtn').off('click').on('click', function () {
          const nextQuestion = findNextUnansweredQuestion(currentQuestion);
          if (nextQuestion !== -1) {
            currentQuestion = nextQuestion;
            showQuestion(currentQuestion);
          } else if (currentQuestion < questions.length - 1) {
            currentQuestion++;
            showQuestion(currentQuestion);
          }
        });
      });
    } else {
      alert('Error loading questions: ' + data.error);
    }
  });
}

function showQuestion(index) {
  const question = questions[index];
  let hasMultipleAnswers = Array.isArray(question.answer);

  let html = `
                <div class="question-card" data-question-id="${question.id}">
                    <h5 class="card-title">
                        Question ${question.id}
                        ${answers[question.id]?.answered ?
      `<span class="question-status-icon ${answers[question.id].correct ? 'status-correct' : 'status-incorrect'}">
                                ${answers[question.id].correct ? '✓' : '✗'}
                            </span>` :
      '<span class="question-status-icon status-unanswered">•</span>'}
                    </h5>
                    <p class="question-text">${question.question}</p>`;

  if (hasMultipleAnswers) {
    html += `
                    <div class="multiple-answer-notice">
                        This question has multiple correct answers. Select all that apply.
                    </div>`;
  }

  html += `<div class="options">`;

  for (const [key, value] of Object.entries(question.options)) {
    const isAnswered = answers[question.id]?.answered;
    const isSelected = isAnswered && (hasMultipleAnswers ?
      answers[question.id].answer.includes(key) :
      answers[question.id].answer[0] === key);
    const isCorrect = hasMultipleAnswers ?
      question.answer.includes(key) :
      question.answer === key;

    let optionClass = 'option-label';
    if (isSelected) optionClass += ' selected';
    if (isAnswered) {
      optionClass += ' disabled';
      if (isSelected && isCorrect) optionClass += ' option-correct';
      if (isSelected && !isCorrect) optionClass += ' option-incorrect';
      if (!isSelected && isCorrect) optionClass += ' option-correct';
    }

    html += `
                    <label class="${optionClass}">
                        <input type="${hasMultipleAnswers ? 'checkbox' : 'radio'}" 
                               name="question${question.id}" 
                               value="${key}" 
                               ${isSelected ? 'checked' : ''} 
                               ${isAnswered ? 'disabled' : ''}>
                        ${key}) ${value}
                    </label>`;
  }

  html += `
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-success submit-answer" onclick="submitAnswer(${question.id})" ${answers[question.id]?.answered ? 'disabled' : ''}>
                            Submit Answer
                        </button>
                    </div>
                    <div class="answer-status" id="status-${question.id}"></div>
                    <div class="explanation" id="explanation-${question.id}"></div>
                </div>`;

  $('#questionContainer').html(html);

  if (hasMultipleAnswers) {
    $('.multiple-answer-notice').show();
  }

  // Update navigation buttons
  $('#prevBtn').prop('disabled', index === 0);
  $('#nextBtn').prop('disabled', index === questions.length - 1);

  // Show saved answer status if exists
  if (answers[question.id]?.answered) {
    showAnswerStatus(question.id);
  }
}

function submitAnswer(questionId) {
  const question = questions.find(q => q.id == questionId);
  const hasMultipleAnswers = Array.isArray(question.answer);

  let selectedAnswers = [];
  if (hasMultipleAnswers) {
    $(`.question-card[data-question-id="${questionId}"] input[type="checkbox"]:checked`).each(function () {
      selectedAnswers.push($(this).val());
    });
    if (selectedAnswers.length === 0) {
      alert('Please select at least one answer.');
      return;
    }
  } else {
    const selected = $(`.question-card[data-question-id="${questionId}"] input[type="radio"]:checked`).val();
    if (!selected) {
      alert('Please select an answer.');
      return;
    }
    selectedAnswers = [selected];
  }

  const isCorrect = hasMultipleAnswers ?
    arraysEqual(selectedAnswers.sort(), question.answer.sort()) :
    selectedAnswers[0] === question.answer;

  answers[questionId] = {
    answer: selectedAnswers,
    answered: true,
    correct: isCorrect
  };

  if (isCorrect) {
    correctAnswers++;
  }

  updateProgress();

  // Prepare the answer data in the format ExamManager expects
  const answer = {
    questionId: parseInt(questionId, 10),
    examFile: currentFile,
    answer: selectedAnswers,
    correctAnswer: question.answer,
    correct: isCorrect,
    timestamp: Math.floor(Date.now() / 1000),
    answered: true
  };

  console.log('Preparing answer:', answer);

  // Prepare the answer with questionId as key
  const answerData = {};
  answerData[answer.questionId] = {
    questionNumber: answer.questionId,
    answer: answer.answer,
    correctAnswer: answer.correctAnswer,
    correct: answer.correct,
    answered: true,
    timestamp: Math.floor(Date.now() / 1000)
  };

  // Create the request data
  const requestData = {
    answers: JSON.stringify({
      questionId: answer.questionId,
      answer: answer.answer,
      file: currentFile,
      correct: answer.correct,
      correctAnswer: answer.correctAnswer
    })
  };

  console.log('Submitting answer:', {
    data: JSON.parse(requestData.answers)
  });

  console.log('Sending request:', requestData);

  $.post('api/save-progress.php', requestData, function (response) {
    console.log('Server response:', response);

    if (response.success) {
      console.log('Answer saved successfully');
      showAnswerStatus(questionId);

      const card = $(`.question-card[data-question-id="${questionId}"]`);

      // Disable inputs and button
      card.find('input').prop('disabled', true);
      card.find('.submit-answer').prop('disabled', true);

      // Mark correct/incorrect answers
      card.find('.option-label').each(function () {
        const value = $(this).find('input').val();
        const isSelected = selectedAnswers.includes(value);
        const isCorrect = hasMultipleAnswers ?
          question.answer.includes(value) :
          question.answer === value;

        if (isSelected && isCorrect) {
          $(this).addClass('option-correct');
        } else if (isSelected && !isCorrect) {
          $(this).addClass('option-incorrect');
        } else if (!isSelected && isCorrect) {
          $(this).addClass('option-correct');
        }
      });

      // Update stats
      loadAndUpdateHomeStats();
    } else {
      console.error('Error saving answer:', response.error);
      alert('Error saving answer: ' + response.error);
    }
  }, 'json').fail(function (xhr, status, error) {
    console.error('AJAX request failed:', {
      status: status,
      error: error,
      response: xhr.responseText
    });
    alert('Failed to save answer. Please try again.');
  });
}

function showAnswerStatus(questionId) {
  const question = questions.find(q => q.id == questionId);
  const answer = answers[questionId];
  const statusDiv = $(`#status-${questionId}`);
  const explanationDiv = $(`#explanation-${questionId}`);
  const hasMultipleAnswers = Array.isArray(question.answer);

  statusDiv.show();

  if (answer.correct) {
    statusDiv.html('Correct! 🎉').removeClass('incorrect').addClass('correct');
  } else {
    let correctAnswer = hasMultipleAnswers ?
      question.answer.join(', ') :
      question.answer;
    statusDiv.html(`Incorrect. The correct answer is: ${correctAnswer}`).removeClass('correct').addClass('incorrect');
  }

  if (question.explanation && question.explanation.trim()) {
    explanationDiv.html(`<strong>Explanation:</strong> ${question.explanation}`).show();
  } else {
    explanationDiv.hide();
  }
}

function updateProgress() {
  const answered = Object.values(answers).filter(a => a.answered).length;
  const total = questions.length;
  const percentage = (answered / total) * 100;
  const correctPercentage = (correctAnswers / total * 100).toFixed(1);
  const successRate = answered > 0 ? (correctAnswers / answered * 100).toFixed(1) : 0;

  $('#progressBar').css('width', percentage + '%');
  $('#answeredCount').text(answered);
  $('#answeredCount2').text(answered);
  $('#correctCount').text(correctAnswers);
  $('#correctPercentage').text(correctPercentage);
  $('#successRate').text(successRate);

  // Update progress bar color based on success rate
  const progressBar = $('#progressBar');
  if (successRate >= 70) {
    progressBar.removeClass('bg-warning bg-danger').addClass('bg-success');
  } else if (successRate >= 50) {
    progressBar.removeClass('bg-success bg-danger').addClass('bg-warning');
  } else if (answered > 0) {
    progressBar.removeClass('bg-success bg-warning').addClass('bg-danger');
  }
}

function findFirstUnansweredQuestion() {
  for (let i = 0; i < questions.length; i++) {
    if (!answers[questions[i].id]?.answered) {
      return i;
    }
  }
  return 0; // If all questions are answered, return to the first question
}

function findNextUnansweredQuestion(currentIndex) {
  for (let i = currentIndex + 1; i < questions.length; i++) {
    if (!answers[questions[i].id]?.answered) {
      return i;
    }
  }
  return -1; // No more unanswered questions after current
}

function arraysEqual(a, b) {
  if (a === b) return true;
  if (a == null || b == null) return false;
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; ++i) {
    if (a[i] !== b[i]) return false;
  }
  return true;
}

function updateCardStats(filename, stats) {
  console.log('Updating stats for file:', filename, stats);
  // Find the card for this file
  const card = $(`.question-set-card[data-filename="${filename}"]`);

  if (!stats) {
    console.error('No stats provided for file:', filename);
    return;
  }

  if (card.length) {
    console.log('Found card, updating with stats:', {
      answered: stats.answered,
      total: stats.total,
      correct: stats.correct,
      successRate: stats.answered > 0 ? ((stats.correct / stats.answered) * 100).toFixed(1) : 0
    });
    // Update progress numbers
    card.find('.progress-numbers').text(`${stats.answered}/${stats.total}`);

    // Update progress bar
    const percentage = stats.total > 0 ? (stats.answered / stats.total * 100) : 0;
    card.find('.progress-bar').css('width', `${percentage}%`);

    // Update success rate
    const successRate = stats.answered > 0 ? ((stats.correct / stats.answered) * 100).toFixed(1) : 0;
    card.find('.correct-stats').text(
      `${stats.correct}/${stats.answered} (${successRate}%)`
    );

    console.log('Card stats updated successfully');
  } else {
    console.log('Card not found for file:', filename);
  }
}