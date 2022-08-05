import { createApp } from "vue";

createApp({
    compilerOptions : {
        delimiters: ["${", "}$"]
    },
    data() {
        return {
            timeout: null,
            isLoading: false,
            questions: null
        }
    },
    methods: {
        updateInput(event: KeyboardEvent) {
            if(this.timeout) {
                clearTimeout(this.timeout);
            }
            this.timeout = setTimeout(async () => {
                const value = this.$refs.input.value;
                if(value?.length) {
                    try {
                        this.isLoading = true;
                        const response = await fetch(`/question/search/${ value }`);
                        const body = await response.json();
                        this.questions = JSON.parse(body);
                    } catch (error) {
                        this.questions = null
                    } finally {
                        this.isLoading = false;
                    }
                } else {
                    this.questions = null;
                }
            }, 1000);
        }
    }
}).mount('#search')