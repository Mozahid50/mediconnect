"use strict";

const joinBtn = document.getElementById("joinMeetingBtn");
const leaveBtn = document.getElementById("leaveMeetingBtn");
const callStatus = document.getElementById("callStatus");
const meetingContainer = document.getElementById("jitsiMeeting");

let jitsiApi = null;
let joining = false;

function setStatus(message) {
    callStatus.textContent = message;
}

function setButtons(inCall) {
    joinBtn.disabled = inCall;
    leaveBtn.disabled = !inCall;
}

function cleanupMeeting(message) {
    const oldApi = jitsiApi;
    jitsiApi = null;
    joining = false;

    if (oldApi) {
        try {
            oldApi.dispose();
        } catch (error) {
            console.warn("Jitsi cleanup:", error);
        }
    }

    meetingContainer.replaceChildren();
    setButtons(false);
    setStatus(message);
}

function joinMeeting() {
    if (joining || jitsiApi) return;

    if (typeof JitsiMeetExternalAPI === "undefined") {
        setStatus("Video service could not load. Check your internet and reload.");
        return;
    }

    if (!meetingConfig || !meetingConfig.roomName) {
        setStatus("Appointment room information is missing.");
        return;
    }

    joining = true;
    joinBtn.disabled = true;
    setStatus("Connecting to your video consultation...");

    try {
        const api = new JitsiMeetExternalAPI("meet.jit.si", {
            roomName: meetingConfig.roomName,
            parentNode: meetingContainer,
            width: "100%",
            height: "100%",
            userInfo: {
                displayName: meetingConfig.displayName
            },
            configOverwrite: {
                prejoinPageEnabled: true,
                startWithAudioMuted: false,
                startWithVideoMuted: false
            },
            interfaceConfigOverwrite: {
                TILE_VIEW_MAX_COLUMNS: 2
            }
        });

        jitsiApi = api;

        api.addListener("videoConferenceJoined", () => {
            if (jitsiApi !== api) return;
            joining = false;
            setButtons(true);
            setStatus("Connected! You are in the video consultation.");
        });

        api.addListener("videoConferenceLeft", () => {
            if (jitsiApi === api) {
                cleanupMeeting("You left the video consultation. You can join again.");
            }
        });

        api.addListener("readyToClose", () => {
            if (jitsiApi === api) {
                cleanupMeeting("The consultation ended. You can join again.");
            }
        });

        api.addListener("participantJoined", () => {
            if (jitsiApi === api) {
                setStatus("A participant joined the consultation.");
            }
        });

        api.addListener("participantLeft", () => {
            if (jitsiApi === api) {
                setStatus("A participant left the consultation.");
            }
        });

        api.addListener("cameraError", () => {
            setStatus("Camera access failed. Allow camera permission in your browser.");
        });

        api.addListener("micError", () => {
            setStatus("Microphone access failed. Allow microphone permission in your browser.");
        });

        setStatus("Video room opened. Allow camera and microphone access to continue.");

    } catch (error) {
        console.error("Jitsi startup error:", error);
        cleanupMeeting("Unable to start the call. Please reload and try again.");
    }
}

function leaveMeeting() {
    if (!jitsiApi) return;

    setStatus("Leaving the consultation...");

    try {
        jitsiApi.executeCommand("hangup");
    } catch (error) {
        console.warn("Hangup error:", error);
        cleanupMeeting("You left the video consultation.");
    }
}

joinBtn.addEventListener("click", joinMeeting);
leaveBtn.addEventListener("click", leaveMeeting);

setButtons(false);