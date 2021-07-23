'use strict';

const photoCenter = document.getElementById('photoCenter');
const photoRight = document.getElementById('photoRight');
const photoLeft = document.getElementById('photoLeft');
const textCenter = document.getElementById('text-center');
const textRight = document.getElementById('text-right');
const textLeft = document.getElementById('text-left');
const recapCenter = document.getElementById('recapture-center');
const recapRight = document.getElementById('recapture-right');
const recapLeft = document.getElementById('recapture-left');
const buttonSubmit = document.getElementById('button-submit');

// image
const imgCenter = document.getElementById('img-center');
const imgLeft = document.getElementById('img-left');
const imgRight = document.getElementById('img-right');
const rotateImg = document.getElementById('rotateImg');
var flagSubmit = true, flagRightImg = false, flagCenterImg = false, flagLeftImg = false;
// Video element where stream will be placed.
const localVideo = document.querySelector('video');
const video = document.getElementById('camera');

//value get from php send
var id=-1, indexImg = 0, textShow="", token, serverurl, newUrlMoodle, timeCheckin,idAtt, sessionid;

let arrImgRotate = [];

function init(Y, sessionId, myserverurl,arrImgRotateMoodle,urlMoodle,myToken,moodleTimeCheckin,id,moodlesessionid) {
    try {
        id = sessionId;
        arrImgRotate = arrImgRotateMoodle;
        serverurl = myserverurl;
        token = myToken;
        idAtt=id;
        timeCheckin = moodleTimeCheckin - 1;
        sessionid = moodlesessionid;
        newUrlMoodle = urlMoodle;
        Promise.all([faceapi.nets.faceLandmark68Net.loadFromUri(urlMoodle+'blocks/user_faces/weights'),
            faceapi.nets.faceExpressionNet.loadFromUri(urlMoodle+'blocks/user_faces/weights'),
            faceapi.nets.mtcnn.loadFromUri(urlMoodle+'blocks/user_faces/weights'),
            faceapi.nets.ssdMobilenetv1.loadFromUri(urlMoodle+"blocks/user_faces/weights")
        ]);
        showImgOrCanvas(false);
        setInterval(()=>{
            if (timeCheckin > 0){
                document.getElementById("time").innerHTML = timeCheckin;
                timeCheckin = timeCheckin - 1;
            }
            else {
                window.location.href = `${urlMoodle}mod/attendance/view.php?id=${id}`;
            }
        },1000);
    } catch (e) {
        console.log(e);
    }
}


// show images or canvas
function showImgOrCanvas(isShowImg) {
    let img = "none", canvas = "none";
    if (isShowImg) {
        img = "block";
    } else {
        canvas = "block";
    }
    imgCenter.style.display = img;
    imgLeft.style.display = img;
    imgRight.style.display = img;
    photoCenter.style.display = canvas;
    photoLeft.style.display = canvas;
    photoRight.style.display = canvas;

    textCenter.style.display = canvas;
    textLeft.style.display = canvas;
    textRight.style.display = canvas;
    recapCenter.style.display = img;
    recapLeft.style.display = img;
    recapRight.style.display = img;
}

function myuser(Y, initvariables) {
    user = initvariables;
}

// Handles success by adding the MediaStream to the video element.
function gotLocalMediaStream(mediaStream) {
    localVideo.srcObject = mediaStream;
    document.getElementById("camera").style.display = "block";
    rotateImg.style.display = "block";
    document.getElementById("background-camera").style.display = "block";
    document.getElementById("mytext").innerHTML = "Khuôn mặt để chính giữa vừa kích thước.";
}

// Handles error by logging a message to the console with the error message.
function handleLocalMediaStreamError(error) {
    console.log('navigator.getUserMedia error: ', error);
    document.getElementById("container-loading").style.display = "none";
    document.getElementById("camera").style.display = "block";
    document.getElementById("background-camera").style.display = "block";
}

// Initializes media stream.
async function handleClickOpenCam() {
    document.getElementById("button-snap").style.display = "none";
    document.getElementById("dontshow").style.display = "block";
    document.getElementById("detect-model").style.display = "block";

    var ctxCenter = photoCenter.getContext('2d');
    var ctxLeft = photoLeft.getContext('2d');
    var ctxRight = photoRight.getContext('2d');

    var imgLeft = new Image();
    var imgRight = new Image();
    var imgCenter = new Image();

    imgCenter.onload = function () {
        ctxCenter.drawImage(imgCenter, 0, 0, 160, 160); // Or at whatever offset you like
    };
    imgLeft.onload = function () {
        ctxLeft.drawImage(imgLeft, 0, 0, 160, 160); // Or at whatever offset you like
    };
    imgRight.onload = function () {
        ctxRight.drawImage(imgRight, 0, 0, 160, 160); // Or at whatever offset you like
    };
    await navigator.mediaDevices.getUserMedia({video: true})
        .then(gotLocalMediaStream).catch(handleLocalMediaStreamError);

}

function grabWebCamVideo() {
    console.log('Getting user media (video) ...');
    navigator.mediaDevices.getUserMedia({
        video: true
    })
        .then(gotStream)
        .catch(function (e) {
            alert('getUserMedia() error: ' + e.name);
        });
}


function snapPhoto(photoSnap, sourceCanvas) {
    const photoContext = photoSnap.getContext('2d');
    photoContext.drawImage(sourceCanvas, 0, 0, 160, 160);
    if (!isCanvasBlank(photoRight) && !isCanvasBlank(photoLeft) && !isCanvasBlank(photoCenter)) {
        buttonSubmit.classList.remove("button-disable");
    }

    rotateImg.src = "";

    if (!flagLeftImg && indexImg === 2) {
        rotateImg.src = arrImgRotate[2];
    }
    if (!flagRightImg && indexImg === 1) {
        rotateImg.src = arrImgRotate[1];
    }
    if (!flagCenterImg && indexImg === 0) {
        rotateImg.src = arrImgRotate[0];
    }
    if (arrImgRotate.findIndex((e) => rotateImg.src.includes(e)) === -1) {
        rotateImg.style.display = "none";
    }
    //show(photo, sendBtn);
}

function handleResetPicture() {
    showImgOrCanvas(false);
    indexImg = 0;
    flagRightImg = false;
    flagCenterImg = false;
    flagLeftImg = false;
    buttonSubmit.classList.add("button-disable");
    photoCenter.getContext('2d').clearRect(0, 0, photoCenter.width, photoCenter.height);
    photoRight.getContext('2d').clearRect(0, 0, photoRight.width, photoRight.height);
    photoLeft.getContext('2d').clearRect(0, 0, photoLeft.width, photoLeft.height);
    textCenter.style.display = "block";
    textRight.style.display = "block";
    textLeft.style.display = "block";
    rotateImg.src = arrImgRotate[0];
    rotateImg.style.display = "block";
}

function handleResetLeftPicture() {
    imgLeft.style.display = "none";
    photoLeft.style.display = "block";
    buttonSubmit.classList.add("button-disable");
    photoLeft.getContext('2d').clearRect(0, 0, photoLeft.width, photoLeft.height);
    textLeft.style.display = "block";
    rotateImg.style.display = "block";
    flagLeftImg = false;
    if (indexImg > 2) {
        indexImg = 2;
    }
    if (indexImg === 2 || flagRightImg && flagCenterImg) {
        indexImg = 2;
        rotateImg.src = arrImgRotate[2];
        textShow = "Xoay trái khuôn mặt từ 12 - 36 độ";
    }
}

function handleResetRightPicture() {
    imgRight.style.display = "none";
    photoRight.style.display = "block";
    buttonSubmit.classList.add("button-disable");
    photoRight.getContext('2d').clearRect(0, 0, photoLeft.width, photoLeft.height);
    textRight.style.display = "block";
    rotateImg.style.display = "block";
    flagRightImg = false;

    if (indexImg > 1) {
        indexImg = 1;
    }
    if (indexImg === 1) {
        rotateImg.src = arrImgRotate[1];
        textShow = "Xoay phải khuôn mặt từ 12 - 36 độ";
    }
}

function handleResetCenterPicture() {
    indexImg = 0;
    imgCenter.style.display = "none";
    photoCenter.style.display = "block";
    buttonSubmit.classList.add("button-disable");
    photoCenter.getContext('2d').clearRect(0, 0, photoLeft.width, photoLeft.height);
    textCenter.style.display = "block";
    textShow = "Giữ khuôn mặt ở giữa.";
    rotateImg.src = arrImgRotate[0];
    rotateImg.style.display = "block";
    flagCenterImg = false;
}

async function handleSubmitPicture() {
    if (flagSubmit && !isCanvasBlank(photoRight) && !isCanvasBlank(photoLeft) && !isCanvasBlank(photoCenter)) {
        document.getElementById("loader-sending").style.display = "inline-block";
        buttonSubmit.classList.add("button-disable");
        flagSubmit = false;
        try {
            const dataCenter = photoCenter.toDataURL().split(",")[1];
            const dataLeft = photoLeft.toDataURL().split(",")[1];
            const dataRight = photoRight.toDataURL().split(",")[1];
            const formData = new FormData();
            const images = [dataLeft,dataCenter,dataRight]
            formData.append("front", dataCenter);
            formData.append("left", dataLeft);
            formData.append("right", dataRight);
            formData.append("sessionid",sessionid);
            await axios({
                method: 'post',
                url: serverurl + "/face/users/verify" ,
                data: formData,
                headers:{
                    'moodle': newUrlMoodle.slice(0,-1),
                    'Authorization': token
                }
            })
                .then(function (response) {
                    console.log(response.data);
                    if (response.data.status === 200 || response.data.status === 201){
                        alert('Điểm danh thành công');
                        window.location.replace(`${newUrlMoodle}mod/attendance/view.php?id=${idAtt}`);
                    } else {
                        document.getElementById("loader-sending").style.display = "none";
                        buttonSubmit.classList.remove("button-disable");
                        flagSubmit = true;
                        alert(response.data.message);
                    }

                });
        } catch (e) {
            document.getElementById("loader-sending").style.display = "none";
            buttonSubmit.classList.remove("button-disable");
            flagSubmit = true;
            console.log(e);
        }
    }
}

video.addEventListener("play", async () => {
    const canvas = faceapi.createCanvasFromMedia(video);
    canvas.id = "mycanvas";
    canvas.style.top = 0;
    document.getElementById("dontshow").append(canvas);
    const displaySize = {width: 500, height: 375};
    faceapi.matchDimensions(canvas, displaySize);
    const tinyModel = new faceapi.MtcnnOptions({
        scaleFactor: 0.9,
        minFaceSize: 300
    });
    const ssdModel = new faceapi.SsdMobilenetv1Options({scaleFactor: 0.9, minFaceSize: 300});
    const model = document.getElementById("model");
    document.getElementById("container-loading").style.display = "none";
    const myInterval = setInterval(async () => {
        const res = await faceapi.detectSingleFace(video, model.value === "tiny" ? tinyModel : ssdModel)
            .withFaceLandmarks().withFaceExpressions();
        //document.getElementById("mytext").innerHTML = JSON.stringify(res);

        if (res?.alignedRect._box._width > 120 && res.alignedRect._box._height > 105) {
            if (res && res.expressions.neutral > 0.8) {
                //clearInterval(myInterval);

                const eye_right = getMeanPosition(res.landmarks.getRightEye());
                const eye_left = getMeanPosition(res.landmarks.getLeftEye());
                const nose = getMeanPosition(res.landmarks.getNose());
                const mouth = getMeanPosition(res.landmarks.getMouth());
                const jaw = getTop(res.landmarks.getJawOutline());

                const rx = (jaw - mouth[1]) / res.detection._box._height + 0.5;
                const ry = (eye_left[0] + (eye_right[0] - eye_left[0]) / 2 - nose[0]) /
                    res.detection._box._width;
                const detection = res.detection._box;

                const regionsToExtract = [
                    new faceapi.Rect(detection._x, detection._y, detection._width, detection._height)
                ]
                // actually extractFaces is meant to extract face regions from bounding boxes
                // but you can also use it to extract any other region
                const canvases = await faceapi.extractFaces(video, regionsToExtract);

                let state = "undetected";

                if (!flagRightImg && indexImg === 1) {
                    if (ry < -0.036) {
                        textShow = "Khuôn mặt bạn đang xoay trái quá nhiều.";
                    } else if (ry > 0.012) {
                        textShow = "Khuôn mặt bạn đang xoay phải quá nhiều.";
                    } else textShow = "Xoay phải khuôn mặt từ 12 - 36 độ";
                }
                if (!flagLeftImg && indexImg === 2) {
                    if (ry > 0.036) {
                        textShow = "Khuôn mặt bạn đang xoay phải quá nhiều.";
                    } else if (ry < -0.012) {
                        textShow = "Khuôn mặt bạn đang xoay trái quá nhiều.";
                    } else textShow = "Xoay trái khuôn mặt từ 12 - 36 độ";
                }

                if (!flagCenterImg) {
                    textShow = "Giữ khuôn mặt ở giữa.";
                } else if (flagLeftImg && flagRightImg && flagCenterImg) {
                    textShow = "";
                }
                document.getElementById("mytext").innerHTML = textShow;
                if (res.detection.score > 0.7) {
                    state = "front";
                    if (rx > 0.2) {
                        state = "top";
                    } else {
                        if (indexImg === 2 && ry < -0.012 && ry > -0.036) {
                            if (!flagLeftImg) {
                                indexImg++;
                                textLeft.style.display = "none";
                                recapLeft.style.display = "block";
                                flagLeftImg = true;
                                snapPhoto(photoLeft, canvases[0]);
                            }
                        }
                        if (indexImg === 1 && ry > 0.012 && ry < 0.036) {
                            if (!flagRightImg) {
                                indexImg++;
                                textRight.style.display = "none";
                                recapRight.style.display = "block";
                                flagRightImg = true;
                                snapPhoto(photoRight, canvases[0]);
                            }
                        }
                        if (indexImg === 0 && ry > -0.012 && ry < 0.012) {

                            if (!flagCenterImg) {
                                indexImg++;
                                textCenter.style.display = "none";
                                recapCenter.style.display = "block";
                                flagCenterImg = true;
                                snapPhoto(photoCenter, canvases[0]);
                            }
                        }


                    }
                }
            }
        } else {
            if (!flagCenterImg || !flagRightImg || !flagLeftImg) {
                document.getElementById("mytext").innerHTML = "Khuôn mặt bạn đang hơi xa.";
            } else {
                document.getElementById("mytext").innerHTML = "";
            }

        }

    }, 50);
});

function isCanvasBlank(canvas) {
    try {
        return !canvas.getContext('2d')
            .getImageData(0, 0, canvas.width, canvas.height).data
            .some(channel => channel !== 0);
    } catch (e) {
        console.log(e);
    }
    return false;
}

function getTop(l) {
    return l
        .map((a) => a.y)
        .reduce((a, b) => Math.min(a, b));
}

function getMeanPosition(l) {
    return l
        .map((a) => [a.x, a.y])
        .reduce((a, b) => [a[0] + b[0], a[1] + b[1]])
        .map((a) => a / l.length)
}

function dataURItoBlob(dataURI) {
    // convert base64/URLEncoded data component to raw binary data held in a string
    var byteString;
    if (dataURI.split(',')[0].indexOf('base64') >= 0)
        byteString = atob(dataURI.split(',')[1]);
    else
        byteString = unescape(dataURI.split(',')[1]);

    // separate out the mime component
    var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];

    // write the bytes of the string to a typed array
    var ia = new Uint8Array(byteString.length);
    for (var i = 0; i < byteString.length; i++) {
        ia[i] = byteString.charCodeAt(i);
    }

    return new Blob([ia], {type: mimeString});
}