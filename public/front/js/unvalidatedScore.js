$(document).ready(() => {
    let currentChoice = $('button.multiple-choice');

    currentChoice.on('click', function (e) {
        if ($(e.target).hasClass('event-target')) {
            setQuestionScore($(this));
            setScoreSum();
        }
    });


    let currentChosen = $('button.multiple-choice.current-chosen');
    currentChosen.each(function() {
        setQuestionScore($(this));
        setScoreSum();
    });

    function setQuestionScore(choice) {
        let currentScore = $(choice).data('answer-value');
        let scoreSpan = $(`#score-` + choice.data('question'));
        scoreSpan.data('score', currentScore);
        scoreSpan.text(currentScore.toFixed(2));
    }

    function setScoreSum() {
        let currentScore = $(`#validatedScore`).text();
        let totalScore = 0;
        let scoreSpans = $('.scoreSpan');
        scoreSpans.each(function (key, scoreSpan) {
            let maturityLevel = $(this).attr("data-maturity-level");
            let shouldAverageOnMaturityLevel = maturityLevel !== "";
            if ($(scoreSpan).data('score')) {
                if (shouldAverageOnMaturityLevel) {
                    let division = $(`.scoreSpan[data-maturity-level=${maturityLevel}]`).length;
                    if ($("#questionCount").val() !== undefined) {
                        division = $("#questionCount").val();
                    }
                    totalScore += $(scoreSpan).data('score') / division;
                } else {
                    totalScore += $(scoreSpan).data('score');
                }
            }
        });
        let totalScoreSpan = $(`#unvalidatedScore`);
        totalScoreSpan.data('score', totalScore)
        totalScoreSpan.text(totalScore.toFixed(2));
        if (totalScore > 0 && totalScore > currentScore) {
            $(`#unvalidatedScoreWrapper`).removeClass('d-none');
        } else {
            $(`#unvalidatedScoreWrapper`).addClass('d-none');
        }
    }

});