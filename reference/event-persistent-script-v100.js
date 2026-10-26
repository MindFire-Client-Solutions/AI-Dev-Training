(function () {
    const version = "1.3.8 micMonitor";
    // const version = "1.3.7 support aiAutoHangupSec + resetHangupTimer handle user and assistant talking";
    //const version = "1.3.5 save event 50531 with and without consent pop up";
    // const version = "1.3.4 fix bug make history always return string"
    const csvProcessorEndpoint = "https://8mdmei6e83.execute-api.us-west-1.amazonaws.com/prod/api/csvprocessor";
    const voiceAIEndpoint = "https://2hsjncje8a.execute-api.us-west-1.amazonaws.com/stage/VoiceAIService";

    // Persistent storage for transcript and audio data
    // Encapsulated state object to organize and manage global variables
    const state = {
        transcripts: [],
        usages: [],
        sessionId: null,
        // tempTranscripts: {},
        startDateTime: "",
        stopButton: null,
        // Audio recording state
        ms: null,
        mixedStream: null,
        recorder: null,
        startConversation: null, // Fixed typo from lastConveration
        recordedChunks: [],
        conversationEnded: false,
        transcriptUploadTimer: null,
        lastTranscriptActivity: null,
        // Global audio context for sharing between functions and RTCPeerConnection wrapper
        globalAudioContext: null,
        globalMixedDestination: null,
        hasConsentPopup: false,
        autoHangupTimer: null,  // timer to hangup
    };

    function getDomainPath(purl) {
        let domainPath = window.location.href.split(/[?#]/)[0];
        domainPath = domainPath.replace(/^(https?:\/\/)?(www\.)?/, "");

        if (purl) {
            let index = domainPath.indexOf("/" + purl);
            if (index !== -1) {
                domainPath = domainPath.substring(0, index);
            } else {
                domainPath = domainPath.replace(/\/[^/]+\.[a-z0-9]+$/i, "");  //case begin with purl 
                let prefix = purl + ".";
                if (domainPath.startsWith(prefix)) {
                    domainPath = domainPath.slice(prefix.length);
                }
            }
        }
        domainPath = domainPath.replace(/\/$/, "");
        console.log("domainPath = ", domainPath);  

        return domainPath

    }
    const domainPath = getDomainPath(window._mfpurl);

    // Default event configuration
    const defaultConfig = {
        acctID: window._mfaid,
        domainPath,
        agent_name: '',
        dedupeField: "purl",
        dedupeValue: window._mfpurl,
        contactFieldMap: {
            transcript_url: "event_data.audio_transcript",
            audio_url: "event_data.audio_recording"
        },
        aiConnectMode: "manual",
        aiMuteOnStart: "unmute",
        aiStartButton: "show",
        aiDocumentAttachments: [],
        aiMemory: "ON",
    };
    // Merge defaults with window configuration (window values override defaults)
    const config = { ...defaultConfig, ...(window._event_config || {}) };

    // Cache debug flag result at initialization - Fix #8
    const debugEnabled = (() => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has('debug') && urlParams.get('debug') === 'true';
    })();

    // Function to check if debug mode is enabled
    function isDebugEnabled() {
        return debugEnabled;
    }

    // Custom log function that respects debug flag
    function log(...args) {
        if (isDebugEnabled()) {
            console.log.apply(console, ["Voice Persistent:", ...args]);
        }
        // If not in debug mode, silently ignore
    }

    function waitForShadowRoot(component, callback) {
        const check = () => {
            if (component.shadowRoot?.querySelector('#start-button')) {
                callback();
            } else {
                setTimeout(check, 100);
            }
        };
        check();
    }

    function waitForStartButton() {
        const startBtn = voiceComponent.shadowRoot?.querySelector('#start-button');
        if (startBtn) {
            if (config.aiStartButton === 'hide') {
                startBtn.style.display = 'none';
            }
            if (config.aiConnectMode === 'auto') {
                startBtn.click();
            }
        } else if (Date.now() - startTime < 15000) { // Max 15 sec
            // Hide loading animation if conditions met
            if (config.aiStartButton === 'hide' && config.aiConnectMode === 'auto') {
                const loadingContainer = voiceComponent.shadowRoot?.querySelector('.loading-container');
                if (loadingContainer) {
                    loadingContainer.style.display = 'none';
                }
            }
            setTimeout(waitForStartButton, 500);
        } else {
            console.warn('Start button not found after 15s');
        }
    }

    // return string
    const fetchHistoryPromise = (async () => {
        if((config.aiMemory === "OFF") || (config.aiMemory === false) || (config.aiMemory === "off")){
            console.log("history disabled",`aiMemory=${config.aiMemory}`);
            return "";
        }
        const summary_url = `https://mfdavinci.s3.us-west-1.amazonaws.com/file/voiceai/${window._mfaid}_${window._mfpurl}_summary.json`;
        const realtimeSummary_url = `${voiceAIEndpoint}?cmd=voiceAI.summaryTranscript&purl=${window._mfpurl}&acctID=${window._mfaid}`;
    
        const trySummary = fetch(summary_url)
            .then(res => res.ok ? res.text() : null)
            .then(text => {
                if (!text) return null;
                const parsed = JSON.parse(text);
                return parsed;
            })
            .catch(() => null);
    
        const tryRealtimeSummary = fetch(realtimeSummary_url)
            .then(res => res.ok ? res.text() : null)
            .then(text => text ? JSON.parse(text) : null)
            .catch(() => null);
    
        const summaryResult = await trySummary;
        console.log("summaryResult",summaryResult);
        if (summaryResult) {
            console.log("used save summary",summaryResult);
            return JSON.stringify(summaryResult);
        }
    
        const realtimeSummaryTranscriptResult = await tryRealtimeSummary;
        console.log("realtimeSummaryTranscriptResult",realtimeSummaryTranscriptResult);
        if (realtimeSummaryTranscriptResult) {
            console.log("used realtime summary");
            if(realtimeSummaryTranscriptResult.summary){
                try{
                    let summary = JSON.parse(realtimeSummaryTranscriptResult.summary);
                    return JSON.stringify(summary);
                }catch(ex){
                }
            }
            return JSON.stringify(realtimeSummaryTranscriptResult);
        }
        return "";
    })();

    // --- start immediately at module load ---
    const attachmentsPromise = (async () => {
        const attachments =
            typeof config.aiDocumentAttachments === "function"
                ? config.aiDocumentAttachments()
                : config.aiDocumentAttachments || [];

        log("attachments", attachments, config);

        try {
            return await fetchAttachments(attachments);
        } catch (err) {
            console.error("Attachments fetch failed:", err);
            return [];
        }
    })();
    

    async function resolveConversationContext(voiceChatComponent) {
        // --- wait for shadowRoot separately ---
        waitForShadowRoot(voiceChatComponent, async () => {
            const startBtn = voiceChatComponent.shadowRoot.querySelector('#start-button');
            startBtn.disabled = true;

            const consentOverlay = voiceChatComponent.shadowRoot.querySelector('#consent-overlay');
            if (consentOverlay) {
                state.hasConsentPopup = true;
                // const cancelBtn = voiceChatComponent.shadowRoot.querySelector('#consent-cancel-btn');
                // if (cancelBtn) {
                //     cancelBtn.addEventListener('click', () => saveEvent("50531", { event_data: { consent: 'false' } }));
                // }
            }

            try {
                // --- wait for both async results here ---
                const [historyResult, attachmentsResult] = await Promise.all([
                    fetchHistoryPromise,
                    attachmentsPromise
                ]);

                // --- merge results ---
                const history = [];

                if (historyResult?.length > 0) {
                    history.push(historyResult);
                }else{
                    history.push("No previous conversation exist");
                }

                if (attachmentsResult?.length > 0) {
                    history.push(...attachmentsResult);
                }

                // --- assign final result ---
                voiceChatComponent.history = history;
                log("Resolved conversation context:", history);

            } catch (err) {
                console.error("resolveConversationContext failed:", err);
                voiceChatComponent.history = [];
            } finally {
                startBtn.disabled = false;
                waitForStartButton();
            }
        });
    }

    async function fetchAttachments(attachments) {
        const results = await Promise.allSettled(
            attachments.map(async (att) => {
                const res = await fetch(att.url);
                if (!res.ok) throw new Error(`Failed: ${att.url}`);
                const content = await res.text();

                const { url, ...rest } = att;

                // try parse JSON
                try {
                    const parsed = JSON.parse(content);

                    // merge parsed JSON into object (no content field)
                    return {
                        ...rest,
                        ...parsed
                    };
                } catch {
                    // fallback: keep raw content
                    return {
                        ...rest,
                        content
                    };
                }
            })
        );

        return results
            .filter(r => r.status === "fulfilled")
            .map(r => r.value);
    }
    // Remove unused variables: audioData (commented), lastSave, autoEndSession, mixedDestination, ms, audioContext

    // Event Saved parameters (constants, no change needed)
    
    



    // Make it globally accessible
    window.debugLog = log;
    log("version", version);
    log('Config received:', config);

    // Initialize global audio context early so RTCPeerConnection wrapper can use it
    state.globalAudioContext = new (window.AudioContext || window.webkitAudioContext)();
    state.globalMixedDestination = state.globalAudioContext.createMediaStreamDestination();

    const voiceComponent = document.querySelector('voice-chat-component');

    if (voiceComponent) {
        const startTime = Date.now();

        resolveConversationContext(voiceComponent);

        // Use event delegation to catch all events from voice component
        voiceComponent.addEventListener('voice.consent.declined', (event) => {
            log('Consent declined:', event.detail);
            saveEvent("50531", { event_data: { consent: 'false' } });
        });

        voiceComponent.addEventListener('session.created', (event) => {
            log('Session created:', event.detail);
            // Mute on start
            log('aiMuteOnStart:', config.aiMuteOnStart);
            if (config.aiMuteOnStart === 'mute') {
                const muteBtn = voiceComponent.shadowRoot?.querySelector('#mute-btn');
                log('muteBtn:', muteBtn);
                if (muteBtn) {
                    muteBtn.click();
                }
            }
            state.sessionId = event.detail.sessionId || event.detail.id || crypto.randomUUID();  // Generate random UUID like KCVoiceAI
            state.transcripts = [];
            state.usages = [];
            state.recordedChunks = [];
            state.conversationEnded = false;
            state.lastTranscriptActivity = Date.now(); // Initialize activity timestamp at session start
            saveEvent("50531", state.hasConsentPopup ? { event_data: { consent: 'true' } } : undefined);

            // Fix #3: Wrap setupRecorder in try/catch so session initialization continues even if recorder fails
            try {
                setupRecorder();
            } catch (recorderError) {
                console.error('Failed to setup recorder:', recorderError);
                // Session continues without recording - still track transcripts
            }

            state.startDateTime = new Date().toISOString();
            //hookEndButton();
        });

        // Mirror KCVoiceAI.vue: if (msg.type === "conversation.item.input_audio_transcription.completed")
        // voiceComponent.addEventListener('conversation.item.input_audio_transcription.completed', (event) => {
        //     log('User transcript received:', event.detail);
        //     if(event.detail.transcript || event.detail.text){
        //         const transcriptData = {
        //             from: 'user',
        //             timestamp: Date.now(),
        //             sessionId: state.sessionId,
        //             text: event.detail.transcript || event.detail.text,
        //             source: 'audio_transcription'
        //         };
        //         state.transcripts.push(transcriptData);
        //     }
        //     state.lastTranscriptActivity = Date.now(); // Track recent transcription activity
        //     persistTranscriptData();
        // });
        voiceComponent.addEventListener('response.output_audio_transcript.done', (event) => {
            log('Agent transcript received:', event);
            if (event?.detail?.transcript) {
                const transcriptData = {
                    from: 'assistant',
                    timestamp: Date.now(),
                    sessionId: state.sessionId,
                    text: event.detail.transcript,
                    source: 'output_audio_transcript'
                };
                state.transcripts.push(transcriptData);
                state.lastTranscriptActivity = Date.now();
                persistTranscriptData();
            }
        });

        voiceComponent.addEventListener('conversation.item.input_audio_transcription.completed', (event) => {
            log('User transcript received:', event);
            // Handle text messages
            if (event?.detail?.transcript) {
                const transcriptData = {
                    from: 'user',
                    timestamp: Date.now(),
                    sessionId: state.sessionId,
                    text: event.detail.transcript,
                    source: 'input_audio_transcription'
                };
                state.transcripts.push(transcriptData);
                state.lastTranscriptActivity = Date.now();
                persistTranscriptData();
            }
        });

        // Mirror KCVoiceAI.vue: if (msg.type === "conversation.item.created")
        // voiceComponent.addEventListener('conversation.item.created', (event) => {
        //     log('Conversation item created:', event.detail);
        //     // Handle text messages
        //     const text = event.detail?.content?.[0]?.text;
        //     if (text) {
        //         const transcriptData = {
        //             from: 'user',
        //             timestamp: Date.now(),
        //             sessionId: state.sessionId,
        //             text: text,
        //             source: 'text_input'
        //         };
        //         state.transcripts.push(transcriptData);
        //         state.lastTranscriptActivity = Date.now(); // Track recent transcription activity
        //         persistTranscriptData();
        //     }
        // });

        // Mirror KCVoiceAI.vue: if (msg.type === "response.done")
        voiceComponent.addEventListener('response.done', (event) => {
            log('Usage received:', event.detail);
            // Collect assistant transcripts and audio data
            const usage = event.detail?.response?.usage;
            if (usage) {
                state.usages.push(event.detail?.response);
                persistTranscriptData();
                // for (const item of output) {
                //     if (item.type === 'message') {
                //         for (const content of item.content) {
                //             // Handle transcript
                //             const transcriptText = content.transcript || content.text;
                //             if (transcriptText) {
                //                 // const transcriptData = {
                //                 //     from: 'assistant',
                //                 //     timestamp: Date.now(),
                //                 //     sessionId: state.sessionId,
                //                 //     text: transcriptText,
                //                 //     audioTime: event.detail.audioTime || 0,
                //                 //     usage: event.detail?.response?.usage
                //                 // };
                //                 // transcripts.push(transcriptData);
                //                 // Store temp transcript keyed by response_id like KCVoiceAI.vue
                //                 // const responseId = event.detail.response?.id;
                //                 // if (responseId && !state.tempTranscripts[responseId]) {
                //                 //     state.tempTranscripts[responseId] = [];
                //                 // }
                //                 // if (responseId) {
                //                 //     state.tempTranscripts[responseId].push(transcriptData);

                //                 //     // Fix #2 & #4: Fallback mechanism with cleanup - move temp transcripts to main list after 10s
                //                 //     // This prevents orphaned transcripts if buffer events never fire
                //                 //     const timeoutId = setTimeout(() => {
                //                 //         if (state.tempTranscripts[responseId]) {
                //                 //             log('Fallback - Moving orphaned temp transcripts for response:', responseId);
                //                 //             for (const m of state.tempTranscripts[responseId]) {
                //                 //                 state.transcripts.push({ ...m, utc: Date.now() });
                //                 //             }
                //                 //             delete state.tempTranscripts[responseId];
                //                 //             persistTranscriptData();
                //                 //         }
                //                 //     }, 10000); // 10 second fallback

                //                 //     // Store timeout ID so we can clear it if buffer events fire normally
                //                 //     state.tempTranscripts[responseId].timeoutId = timeoutId;
                //                 // }
                //             }
                //         }
                //     }
                //     // Handle audio data if available (removed as unused)
                // }
            }
            // persistTranscriptData();
        });

        // Mirror KCVoiceAI.vue: if ((msg.type == "output_audio_buffer.stopped") || (msg.type == "output_audio_buffer.cleared"))
        voiceComponent.addEventListener('output_audio_buffer.stopped', (event) => {
            log('Audio buffer stopped:', event.detail);
            //handleAudioBufferEvent(event.detail);
        });

        voiceComponent.addEventListener('output_audio_buffer.cleared', (event) => {
            log('Audio buffer cleared:', event.detail);
            //handleAudioBufferEvent(event.detail);
        });
        voiceComponent.addEventListener("voice.conversation.ended", (event) => {
            log('Conversation Ended');
            handleEndConversation();
        });
        voiceComponent.addEventListener("response.output_audio_transcript.delta",(event) => {
            // Assistant talking
            resetHangupTimer();                                        
        });

    } else {
        console.warn('Voice chat component not found in the DOM.');
    }

    // function hookEndButton() {
    //     state.stopButton = voiceComponent.shadowRoot?.querySelector('#stop-btn');
    //     if (state.stopButton) {
    //         state.stopButton.addEventListener('click', () => {
    //             log('Stop button clicked');
    //             handleEndConversation();
    //         });
    //     } else {
    //         console.warn('Stop button not found in the DOM.');
    //     }
    // }
    function resetHangupTimer(){
        log("resetHangupTimer",config.aiAutoHangupSec)   
        if(config.aiAutoHangupSec){
            clearHangupTimer();
            state.autoHangupTimer = setTimeout(async () => {
                try {
                    console.log("Auto hangup")
                    voiceComponent.stopConversation?.();
                } catch (error) {
                    console.error('Failed auto hangup', error);
                }
            }, config.aiAutoHangupSec * 1000); // 5 seconds debounce
        }
    }
    function clearHangupTimer(){
        if (state.autoHangupTimer){
            log("clearHangupTimer")
            clearTimeout(state.autoHangupTimer);
            state.autoHangupTimer = null;
        }
    }

    // Persistence methods mirroring KCVoiceAI.vue behavior
    function persistTranscriptData() {
        resetHangupTimer();
        // Clear any existing timer
        if (state.transcriptUploadTimer) {
            clearTimeout(state.transcriptUploadTimer);
        }

        // Debounce uploads - wait 5 seconds after last transcription before uploading
        // This batches multiple transcript events and reduces network waste
        state.transcriptUploadTimer = setTimeout(async () => {
            try {
                uploadTranscript();
            } catch (error) {
                console.error('Failed to save transcripts:', error);
            }
        }, 5000); // 5 seconds debounce

        // Handle Auto Hangup

    }

    // Audio upload configuration - Hyperparameters for intelligence
    const AUDIO_UPLOAD_CONFIG = {
        ACTIVITY_WINDOW: 15,        // 15 seconds activity window for uploads
        MIN_UPLOAD_INTERVAL: 5,     // Minimum 5 seconds between uploads even during active conversation
    };

    // Performance tracking for network reduction metrics
    let uploadMetrics = {
        totalChecks: 0,          // Total times we evaluated upload need
        actualUploads: 0,        // Actual audio uploads performed
        skippedUploads: 0,       // Uploads we prevented (network savings)
        lastUploadTime: 0,       // Timestamp of last upload
    };

    function persistAudioData() {
        try {
            uploadMetrics.totalChecks++;
            const now = Date.now();

            // Check for recent transcription activity
            const hasRecentActivity = state.lastTranscriptActivity &&
                (now - state.lastTranscriptActivity) < (AUDIO_UPLOAD_CONFIG.ACTIVITY_WINDOW * 1000);

            // Check minimum interval since last upload
            const timeSinceLastUpload = now - uploadMetrics.lastUploadTime;
            const canUpload = timeSinceLastUpload >= (AUDIO_UPLOAD_CONFIG.MIN_UPLOAD_INTERVAL * 1000);

            // Calculate current network savings
            const networkReductionPercent = uploadMetrics.totalChecks > 0
                ? ((uploadMetrics.totalChecks - uploadMetrics.actualUploads) / uploadMetrics.totalChecks * 100)
                : 0;

            if (hasRecentActivity && canUpload) {
                // UPLOAD: Both activity detected AND minimum interval satisfied
                uploadMetrics.actualUploads++;

                log(`UPLOAD #${uploadMetrics.actualUploads}/${uploadMetrics.totalChecks} - ` +
                    `Recent activity detected (${AUDIO_UPLOAD_CONFIG.ACTIVITY_WINDOW}s window), uploading audio. ` +
                    `Network reduction: ${networkReductionPercent.toFixed(1)}%`);

                uploadAudio();
                uploadMetrics.lastUploadTime = now;

            } else {
                // SKIP: Either no activity OR too soon since last upload
                uploadMetrics.skippedUploads++;

                if (!hasRecentActivity) {
                    // Skip reason: No transcript activity in recent window
                    log(`SKIP #${uploadMetrics.skippedUploads} (Activity) - ` +
                        `No transcript activity in last ${AUDIO_UPLOAD_CONFIG.ACTIVITY_WINDOW}s. ` +
                        `Uploads: ${uploadMetrics.actualUploads}/${uploadMetrics.totalChecks} ` +
                        `(${networkReductionPercent.toFixed(1)}% saved)`);
                } else if (!canUpload) {
                    // Skip reason: Upload interval too short
                    log(`SKIP #${uploadMetrics.skippedUploads} (Interval) - ` +
                        `Activity detected but only ${(timeSinceLastUpload / 1000).toFixed(1)}s since last upload ` +
                        `(min ${AUDIO_UPLOAD_CONFIG.MIN_UPLOAD_INTERVAL}s required)`);
                }
            }

        } catch (error) {
            console.error('Audio persistence failed:', error);
        }
    }

    function cleanup() {
        // Final persistence of any remaining data
        if (state.transcripts.length > 0) {
            persistTranscriptData();
        }
        if (state.recordedChunks.length > 0) {
            persistAudioData();
        }
        log('Cleanup completed');
    }

    // Handle audio buffer events like KCVoiceAI.vue
    //function handleAudioBufferEvent(eventDetail) {
    // const responseId = eventDetail.response_id || eventDetail.responseId;
    // log('Audio buffer event for response_id:', responseId);

    // if (responseId && state.tempTranscripts[responseId]) {
    //     // Fix #2: Clear the fallback timeout since buffer events fired normally
    //     if (state.tempTranscripts[responseId].timeoutId) {
    //         clearTimeout(state.tempTranscripts[responseId].timeoutId);
    //     }

    //     // Move transcripts from temp to main list and update timestamps
    //     for (const m of state.tempTranscripts[responseId]) {
    //         // Update timestamp to current time like KCVoiceAI.vue
    //         if (typeof m === 'object' && !m.timeoutId) { // Skip timeoutId property
    //             state.transcripts.push({ ...m, utc: Date.now() });
    //         }
    //     }
    //     delete state.tempTranscripts[responseId];
    //     log('Moved transcripts from temp storage');
    //     log("transcripts", state.transcripts);
    // }
    //}
    async function handleEndConversation() {
        // Prevent double execution
        if (state.conversationEnded) {
            log("Conversation already ended, skipping");
            return;
        }
        state.conversationEnded = true;

        log("handleEndConversation")
        if (state.recorder) {
            state.recorder.stop();
            state.recorder = null;
        }
        log("recordedChunks", state.recordedChunks);

        // Fix #2: Wrap each upload in independent try/catch so sendEndEvent() always executes
        let transcriptS3Url = null;
        let audioResult = { url: null, audioTime: 0 };

        try {
            transcriptS3Url = await uploadTranscript();
        } catch (error) {
            console.error('Failed to upload transcript:', error);
        }

        try {
            audioResult = await uploadAudio();
        } catch (error) {
            console.error('Failed to upload audio:', error);
        }

        // Always send end event regardless of upload success/failure
        sendEndEvent();
    }

    function countTokens() {
        const result = {
            input_tokens: 0,
            output_tokens: 0,
            input_token_details: {
                text_tokens: 0,
                audio_tokens: 0,
                image_tokens: 0,
                cached_tokens: 0,
                cached_tokens_details: {
                    text_tokens: 0,
                    audio_tokens: 0,
                    image_tokens: 0
                }
            },
            output_token_details: {
                text_tokens: 0,
                audio_tokens: 0,
                image_tokens: 0,
                cached_tokens: 0,
                cached_tokens_details: {
                    text_tokens: 0,
                    audio_tokens: 0,
                    image_tokens: 0
                }
            }
        };

        for (const response of state.usages) {
            const u = response.usage;
            if (!u) continue;

            // top-level
            result.input_tokens += u.input_tokens || 0;
            result.output_tokens += u.output_tokens || 0;

            // input details
            if (u.input_token_details) {
                const i = u.input_token_details;
                result.input_token_details.text_tokens += i.text_tokens || 0;
                result.input_token_details.audio_tokens += i.audio_tokens || 0;
                result.input_token_details.image_tokens += i.image_tokens || 0;
                result.input_token_details.cached_tokens += i.cached_tokens || 0;

                if (i.cached_tokens_details) {
                    result.input_token_details.cached_tokens_details.text_tokens +=
                        i.cached_tokens_details.text_tokens || 0;
                    result.input_token_details.cached_tokens_details.audio_tokens +=
                        i.cached_tokens_details.audio_tokens || 0;
                    result.input_token_details.cached_tokens_details.image_tokens +=
                        i.cached_tokens_details.image_tokens || 0;
                }
            }

            // output details
            if (u.output_token_details) {
                const o = u.output_token_details;
                result.output_token_details.text_tokens += o.text_tokens || 0;
                result.output_token_details.audio_tokens += o.audio_tokens || 0;
                result.output_token_details.image_tokens += o.image_tokens || 0;
                result.output_token_details.cached_tokens += o.cached_tokens || 0;

                if (o.cached_tokens_details) {
                    result.output_token_details.cached_tokens_details.text_tokens +=
                        o.cached_tokens_details.text_tokens || 0;
                    result.output_token_details.cached_tokens_details.audio_tokens +=
                        o.cached_tokens_details.audio_tokens || 0;
                    result.output_token_details.cached_tokens_details.image_tokens +=
                        o.cached_tokens_details.image_tokens || 0;
                }
            }
        }

        return result;
    }

    function prettyTranscript() {
        const transcript = state.transcripts
            .map(entry => `${entry.from}: ${entry.text}`)
            .join('\n');
        return transcript;
    }
    function getProperMediaMimeType() {
        if (MediaRecorder.isTypeSupported("audio/mp4")) {
            return "audio/mp4"; // Works on iOS, Android, Desktop
        } else if (MediaRecorder.isTypeSupported("audio/aac")) {
            return "audio/aac"; // Also broadly supported
        } else {
            // Guaranteed fallback (big files)
            return "audio/wav";
        }
    }
    // Fix #7: Removed dead code generateAutoURL() - never called anywhere
    async function getPreSignedURL(type, suffix, _keyPath = "appData", fileName) {
        try {
            let keyPath = `${_keyPath}`;
            const url = `${csvProcessorEndpoint}?cmd=presignedurl&contentType=${encodeURIComponent(type)}&suffix=${suffix}&fileName=${fileName}&keyPath=${keyPath}`;

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    "Authorization": "bearer XYZ",
                }
            });

            if (!response.ok) {
                throw new Error(`Presigned URL request failed: ${response.status} ${response.statusText}`);
            }

            const resp = await response.json();
            log("getPreSignedURL", resp);
            return resp.result;
        } catch (ex) {
            console.error("getPreSignedURL error:", ex);
            return null;
        }
    }
    async function getAudioDuration(audioBlob) {
        try {
            // Fix #5: Reuse existing globalAudioContext, or create one as fallback
            // This prevents leaking AudioContext objects while ensuring functionality
            let audioContext = state.globalAudioContext;
            let shouldClose = false;

            if (!audioContext) {
                // Fallback: create a temporary context if global one is not available
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                shouldClose = true;
            }

            const arrayBuffer = await audioBlob.arrayBuffer();
            const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);

            // Close temporary context to avoid leaks
            if (shouldClose) {
                audioContext.close();
            }

            // Return duration in seconds
            return audioBuffer.duration;
        } catch (error) {
            console.error('Error calculating audio duration:', error);
            return 0; // Return 0 if there's an error
        }
    }
    async function uploadWithPresigned(presignedUrl, blob, type) {
        const response = await fetch(presignedUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': type,
                'x-amz-acl': 'public-read',
            },
            body: blob,
        });

        if (!response.ok) {
            throw new Error(`Upload failed: ${response.status} ${response.statusText}`);
        }

        const url = presignedUrl.split('?')[0];
        return url;
    }
    async function uploadTranscript() {
        // Fix #10: Upload JSON with full metadata alongside plain text for audit/replay capability
        let type = "text/plain; charset=utf-8";
        let transcriptText = prettyTranscript(); // <- Your method to get pretty transcript string
        const audioDuration = await getAudioTime();
        const d = new Date(state.startConversation);
        const totalSeconds = Math.ceil(audioDuration);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        const durationDisplay = minutes > 0 ? `${minutes} min ${seconds} seconds` : `${totalSeconds} seconds`;

        const transcriptWithHeader = `
User Name: ${window._mfpurl}
DateTime: ${d.toLocaleString('en-US', { hour12: true })}
Time Zone: ${Intl.DateTimeFormat().resolvedOptions().timeZone}
Session ID: ${state.sessionId}
Duration: ${durationDisplay}

${transcriptText}
`;
        const transcriptBlob = new Blob([transcriptWithHeader], { type: type });
        log("transcriptText", transcriptText);
        log("transcriptWithHeader", transcriptWithHeader);
        let presignedUrl = await getPreSignedURL(type, "txt", "file/voiceai/", state.sessionId);

        // Fix #1: Check for null presignedUrl before uploading
        if (!presignedUrl) {
            console.error("Failed to get presigned URL for transcript upload");
            return null;
        }

        let url = await uploadWithPresigned(presignedUrl, transcriptBlob, type);
        log("uploadTranscript", "uploaded transcript", url);
        state.transcriptS3Url = url;
        state.tokenCounts = countTokens();
        log("tokenCounts", state.tokenCounts);

        // Also upload detail JSON with full metadata for audit/replay
        try {
            const jsonData = {
                "User Name": window._mfpurl,
                "Session ID": `${state.sessionId}`,
                "Time Zone": `${Intl.DateTimeFormat().resolvedOptions().timeZone}`,
                DateTime: `${d.toLocaleString('en-US', { hour12: true })}`,
                Duration: `${durationDisplay}`,
                tokenCounts: state.tokenCounts,
                transcripts: state.transcripts.map(t => ({
                    from: t.from,
                    text: t.text,
                    timestamp: t.timestamp,
                    source: t.source || 'unknown',
                    audioTime: t.audioTime || 0
                }))
            };
            const jsonBlob = new Blob([JSON.stringify(jsonData, null, 2)], { type: "application/json" });
            const jsonPresignedUrl = await getPreSignedURL("application/json", "json", "file/voiceai/", state.sessionId + "_detail");
            if (jsonPresignedUrl) {
                let url = await uploadWithPresigned(jsonPresignedUrl, jsonBlob, "application/json");
                log("uploadTranscript", "uploaded detail JSON", url);
            }
        } catch (jsonError) {
            console.error("Failed to upload transcript JSON:", jsonError);
            // Don't fail the main upload if JSON fails
        }

        return (url);
    }
    async function getAudioTime(blob) {
        if (blob == null) { // will handle both null and undefined
            const chunks = state.recordedChunks;
            if (!chunks?.length) return 0;

            blob = new Blob(chunks, { type: getProperMediaMimeType() });
        }
        return await getAudioDuration(blob);
    }
    async function uploadAudio() {
        let type = getProperMediaMimeType();
        let suffix = type.split("/")[1];
        suffix = suffix.split(";")[0];
        let mtype = type.split(";")[0];
        let presignedUrl = await getPreSignedURL(mtype, suffix, "file/voiceai/", state.sessionId);
        const blob = new Blob(state.recordedChunks, { type: type });
        let audioTime = await getAudioDuration(blob);
        log("saveConversation", "uploading audio", blob.size, audioTime);
        let url = await uploadWithPresigned(presignedUrl, blob, mtype);
        log("saveConversation", "uploaded audio", url);
        state.audioUrl = url;
        state.audioTime = audioTime;
        return { url, audioTime };
    }

    function sendEndEvent() {
        // clear timer
        log("clear timer");
        if(state.transcriptUploadTimer){
            clearTimeout(state.transcriptUploadTimer);
            state.transcriptUploadTimer = null;
        }
        clearHangupTimer();
        log("sendEndEvent");
        let param = {
            transcriptS3Url: state.transcriptS3Url,
            audios3Url: state.audioUrl,
            tokenCounts: state.tokenCounts,
            audioTime: state.audioTime,
        }
        const event_data = mapToEventData(param);
        log("param", event_data);
        navigator.sendBeacon(`${voiceAIEndpoint}`, JSON.stringify({
            command: "voiceAI.sendBeacon.onEndConversation",
            acctID: window._mfaid,
            purl: window._mfpurl,
            sessionId: state.sessionId,
            ...event_data,
            config,
        }));
    }

    // Reliable page unload detection to ensure conversation end is handled
    window.addEventListener('pagehide', (event) => {
        log('Page hide detected');
        // pagehide fires when page is being unloaded or hidden
        if (event.persisted) {
            // Page might come back (like switching tabs on mobile), don't end conversation
            log('Page may return, skipping end conversation');
            return;
        }

        // Page is being unloaded for good - end conversation if not already ended
        if (!state.conversationEnded && state.sessionId) {
            log('Ending conversation due to page unload');
            // Use navigator.sendBeacon for reliable async delivery
            sendEndEvent();
            // Mark as ended to prevent double execution
            state.conversationEnded = true;
        }
    });

    // Fallback for older browsers or additional safety
    window.addEventListener('beforeunload', cleanup);

    async function saveEvent(event_code, event_data = {}) {
        try {
            if (typeof _MFS === "undefined") {
                console.warn("Voice Assistant SDK: _MFS not available, skipping event save");
                return;
            }

            // Parse dedupefield and dedupevalue into arrays and create filter object
            const dedupeFields = (config.dedupeField || '').split(',').map(f => f.trim()).filter(f => f);
            const dedupeValues = (config.dedupeValue || '').split(',').map(v => v.trim()).filter(v => v);

            const filter = {};
            dedupeFields.forEach((field, index) => {
                if (dedupeValues[index] !== undefined) {
                    filter[field] = dedupeValues[index];
                }
            });

            const record = await _MFS.getRecord(config.acctID, filter, 30);
            if (!record?.jwt) return;

            const eventItem = {
                app_code: "505",
                event_code,
                event_path: config.domainPath,
                event_element: config.agent_name,
                event_date: new Date().toISOString(),
                ...event_data,
            };

            if (record.data?.purl) eventItem.shardKey = record.data.purl;
            log("[VoiceAI] Event Saved:", eventItem);

            await record.addEvent(eventItem);

            if (event_code === "50532" && config.contactFieldMap && typeof config.contactFieldMap === "object") {
                const contact_data = {};
                for (const [field, path] of Object.entries(config.contactFieldMap)) {
                    const value = getValueFromPath(event_data, path);
                    if (value !== undefined) contact_data[field] = value;
                }
                if (Object.keys(contact_data).length > 0) {
                    await record.update(contact_data);
                }
            }
        } catch (error) {
            console.error("Save Event error:", error);
        }
    }

    function mapToEventData(data) {
        const { transcriptS3Url, audios3Url, tokenCounts = {}, audioTime } = data || {};

        return {
            event_data: {
                audio_transcript: transcriptS3Url || "",
                audio_recording: audios3Url || "",
                tokens_used: tokenCounts,
                // tokens_used: {
                //     input_tokens: tokenCounts.input_tokens ?? 0,
                //     output_tokens: tokenCounts.output_tokens ?? 0,
                // },
                audio_time: audioTime ?? 0,
                startdate: state.startDateTime,
                lastupdate: new Date().toISOString(),
            },
        };
    }

    function getValueFromPath(obj, path) {
        return path.split(".").reduce((acc, key) => acc?.[key], obj);
    }



    // RTCPeerConnection wrapper to intercept AI audio
    class AudioMixingRTCPeerConnection extends RTCPeerConnection {
        constructor(options) {
            log("AudioMixingRTCPeerConnection", options);
            super(options);

            // Fix #9: Store reference for AI audio mixing in state instead of window
            state.globalAudioMixingRTC = this;

            // Set up track handler for AI audio
            this.setupTrackHandler();
        }

        setupTrackHandler() {
            // Clear any existing handler and add event listener - Fix #3: Removed duplicate ontrack property
            this.ontrack = null;
            this.addEventListener('track', (event) => {
                log("Got AI remote track:", event.streams[0]);

                // Play AI audio normally
                try {
                    const mediaElement = document.createElement('audio');
                    mediaElement.srcObject = event.streams[0];
                    mediaElement.play();
                } catch (e) {
                    log("Error playing AI audio:", e);
                }

                // Mix into recording stream
                this.handleAIStream(event.streams[0]);
            });
        }

        handleAIStream(aiStream) {
            log("handleAIStream", aiStream);
            if (!state.globalAudioContext) {
                log("No global AudioContext available yet");
                return;
            }

            try {
                if (state.globalAudioContext.state !== 'running') {
                    state.globalAudioContext.resume().catch(log);
                }

                // Create AI audio source and connect to global destination for mixing
                const aiSource = state.globalAudioContext.createMediaStreamSource(aiStream);
                if (state.globalMixedDestination) {
                    aiSource.connect(state.globalMixedDestination);
                    log("Connected AI audio to global mixed destination");
                } else {
                    log("globalMixedDestination not available yet");
                }
            } catch (e) {
                log("Error connecting AI audio:", e);
            }
        }
    }

    async function setupRecorder() {
        // Intercept RTCPeerConnection wrapper to capture AI audio before starting recorder
        // Note: RTCPeerConnection wrapper is already in place from script init

        try {
            // Fix #4: Check if capturedStream exists before using it
            if (!voiceComponent.capturedStream || !voiceComponent.capturedStream.getAudioTracks) {
                console.error('No captured stream available from voice component');
                return;
            }

            // Use component's captured stream but clone tracks for independence
            // This allows mute button to work without affecting recording
            const clonedStream = new MediaStream();
            voiceComponent.capturedStream.getAudioTracks().forEach(track => {
                clonedStream.addTrack(track.clone());
            });
            state.ms = voiceComponent.capturedStream; //clonedStream;
            log("Using cloned microphone stream from component");

            // Use the global AudioContext already created at script startup
            // Connect mic to global destination (AI audio should already be connected here)
            state.globalAudioContext.createMediaStreamSource(state.ms).connect(state.globalMixedDestination);
            state.mixedStream = state.globalMixedDestination.stream;

            log("Connected cloned microphone to global mixed destination");

            startRecord();
        } catch (error) {
            log("Error setting up recorder:", error);
        }
    }

    // Removed mixedDestination (unused)
    async function startRecord() {
        log("startRecord");
        state.startConversation = Date.now();
        startMicMonitor(state.mixedStream);   // <-- Add this
        // Removed lastSave (unused)
        // this.prevConversationCount = this.conversation.length;
        state.recorder = new MediaRecorder(state.mixedStream);
        state.recordedChunks = [];

        state.recorder.ondataavailable = (e) => {
            // console.log("ondataavailable",e);
            if (e.data.size > 0) {
                // let now = Date.now();
                // let idleSec = (now - state.lastConversation) / 1000;
                // log("idle", idleSec);

                // Removed autoEndSession references (unused)
                state.recordedChunks.push(e.data);
                persistAudioData();
                //const isFinalChunk = recorder.state === 'inactive';
                //trySave(isFinalChunk);
            }
        };

        state.recorder.onstop = () => {
            stopMicMonitor();
            log("recorder.onstop");
            // this.trySave(true);
        };
        state.recorder.start(5 * 1000);
    }

    // MIC monitor part
    state.audioContext = null;
    state.analyser = null;
    state.monitorTimer = null;
    state.lastUserAudio = Date.now();
    state.userSpeaking = false;

    function startMicMonitor(stream) {
        state.audioContext = new AudioContext();

        const source = state.audioContext.createMediaStreamSource(stream);

        state.analyser = state.audioContext.createAnalyser();
        state.analyser.fftSize = 512;

        source.connect(state.analyser);

        const data = new Uint8Array(state.analyser.fftSize);

        const THRESHOLD = 0.02;
        let silenceCount = 0;

        state.monitorTimer = setInterval(() => {
            state.analyser.getByteTimeDomainData(data);

            let sum = 0;

            for (let i = 0; i < data.length; i++) {
                const sample = (data[i] - 128) / 128;
                sum += sample * sample;
            }

            const rms = Math.sqrt(sum / data.length);

            if (rms > THRESHOLD) {
                state.lastUserAudio = Date.now();

                silenceCount = 0;

                if (!state.userSpeaking) {
                    state.userSpeaking = true;
                    log("User started speaking");
                    clearHangupTimer();
                }
            } else {
                silenceCount++;

                if (state.userSpeaking && silenceCount > 4) { // ~200ms at 50ms interval
                    state.userSpeaking = false;
                    log("User stopped speaking");
                    resetHangupTimer()
                }
            }
        }, 50);
    }

    function stopMicMonitor() {
        if (state.monitorTimer) {
            clearInterval(state.monitorTimer);
            state.monitorTimer = null;
        }

        if (state.audioContext) {
            state.audioContext.close();
            state.audioContext = null;
        }

        state.analyser = null;
    }


    // Fix #1: RTC wrapper - no DOM dependency, wrap immediately to avoid race condition
    const originalRTCPeerConnection = window.RTCPeerConnection;
    window.RTCPeerConnection = AudioMixingRTCPeerConnection;


    // Dynamically determine the script name from the stack trace
    const scriptName = new Error().stack.split('\n')[1].match(/\/([^\/]+\.js)(?::\d+)?/);
    const displayName = scriptName ? scriptName[1] : 'unknown script';
    log(`Event handler script "${displayName}" version: ${version} loaded - transcript and audio persistence active`);
})();