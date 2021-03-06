<template>
    <div class="ui-block-content create-expense">
        <form>
            <div class="row">
                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 select-type">
                    <label class="control-label">{{ $t('events.expenses.donate_type') }}</label>
                    <multiselect
                        data-vv-name="type"
                        :placeholder="$i18n.t('form.placeholder.choose_type')"
                        :options="types"
                        :value="getNameOfGoal(newExpense.goal_id)"
                        @select="selected"
                        @remove="removed"
                        :searchable="false"
                        v-validate="'required'">
                    </multiselect>
                    <span v-show="errors.has('type')" class="material-input text-danger">
                        {{ errors.first('type') }}
                    </span>
                </div>

                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                    <div class="form-group label-floating" :class="{ 'has-danger': this.errors.has('cost') }">
                        <label class="control-label">{{ $t('form.label.cost') }}</label>
                        <input
                            class="form-control"
                            data-vv-name="cost"
                            type="text"
                            v-model="newExpense.cost"
                            v-validate="'required|numeric|min_value:0|max_value:' + maxCost">
                        <span v-show="errors.has('cost')" class="material-input text-danger">
                            {{ errors.first('cost') }}
                        </span>
                    </div>
                </div>

                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                    <div class="form-group date-time-picker label-floating">
                        <label class="control-label">{{ $t('form.label.time') }}</label>
                        <date-picker :date.sync="time" :formatStand.sync="newExpense.time"></date-picker>
                        <span class="input-group-addon">
                            <svg class="olymp-calendar-icon">
                                <use xlink:href="/frontend/icons/icons.svg#olymp-calendar-icon"></use>
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                    <quill-editor
                        data-vv-name="reason"
                        id="reason"
                        :options="editorOption"
                        v-model="newExpense.reason"
                        v-validate="'required|min:10'">
                    </quill-editor>
                    <span v-show="errors.has('reason')" class="material-input text-danger">
                        {{ errors.first('reason') }}
                    </span>
                </div>
            </div>
        </form>
        <div class="btn-create-expense">
            <button
                href="#"
                class="btn btn-primary btn-md-2 bg-breez"
                data-toggle="modal"
                data-target="#update-header-photo"
                @click="create('expense.list')">
                {{ $t('events.expenses.save_goto_list') }}
            </button>
            <button
                href="#"
                class="btn btn-primary btn-md-2 bg-breez"
                data-toggle="modal"
                data-target="#update-header-photo"
                @click = "create('expense.create')">
                {{ $t('events.expenses.save_create_new_other') }}
            </button>
        </div>
    </div>
</template>

<script type="text/javascript">
    import DatePicker from '../../libs/DatePicker.vue'
    import { post, get } from '../../../helpers/api'
    import Multiselect from 'vue-multiselect'
    import noty from '../../../helpers/noty'
    import { mapActions } from 'vuex'
    export default {
        data: () => ({
            editorOption: {
                placeholder: 'reason',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'code-block'],
                        [{ 'header': 1 }, { 'header': 2 }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'script': 'sub'}, { 'script': 'super' }],
                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                        [{ 'direction': 'rtl' }],
                        [{ 'size': ['small', false, 'large', 'huge'] }],
                        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'font': [] }],
                        [{ 'align': [] }],
                        ['clean']
                    ]
                }
            },
            types: [],
            goal: {},
            time: '',
            newExpense: {
                time: null,
                goal_id: null,
                event_id: null,
                cost: '',
                type: 0,
                reason: ''
            },
            dataGoals: [],
            pageType: 'event'
        }),

        watch: {
            time() {
                this.newExpense.time = this.newExpense.time? this.newExpense.time : this.time
            }
        },

        computed: {
            maxCost() {
                if (!Object.keys(this.goal).length) {
                    return 0
                }

                let receives = this.goal.donations.filter(donation => donation.status == 1)
                let totalReceive = receives.reduce((sum, value) => sum + value.value, 0)
                let totalExpense = this.goal.expenses.reduce((sum, value) => sum + value.cost, 0)

                return totalReceive - totalExpense
            }
        },

        methods: {
            ...mapActions('event', [
                'appendOneAction',
            ]),

            selected(value, id) {
                this.goal = this.dataGoals.filter(dataGoal => dataGoal.donation_type.name == value)[0]
                this.newExpense.goal_id = this.goal.id
                this.newExpense.cost = this.newExpense.cost === null ? '' : null
                this.newExpense.reason = this.newExpense.reason || null
            },

            removed(value, id) {
                this.newExpense.goal_id = null
                this.newExpense.cost = ''
            },

            create(nameRouter) {
                this.$validator.validateAll().then(result => {
                    this.$Progress.start()
                    post('expense', this.newExpense)
                        .then(res => {
                            this.$Progress.finish()
                            noty({
                                text: this.$i18n.t('messages.create_success'),
                                force: false,
                                container: false,
                                type: 'success'
                            })
                            this.newExpense.goal_id =  null
                            this.newExpense.cost =  ''
                            this.newExpense.reason = ''
                            this.callApi()
                            this.appendOneAction({ action: res.data.action })
                            this.$socket.emit('created_action', {
                                newAction: res.data.action,
                                room: `event${this.pageId}`
                            })
                            this.$router.push({ name: nameRouter, params: { event_id: this.pageId }})
                        })
                        .catch(err => {
                            this.$Progress.fail()
                            noty({
                                text: this.$i18n.t('messages.create_fail'),
                                type: 'error',
                                force: false,
                                container: false
                            })
                        })
                })
            },

            getNameOfGoal(id) {
                let goal = this.dataGoals.filter(goal => goal.id == id)

                if (goal.length) {
                    return goal[0].donation_type.name
                }

                return ''
            },

            callApi() {
                get(`goal?event_id=${this.pageId}`).then(res => {
                    this.dataGoals = res.data.goals
                    this.types = this.dataGoals.map(goal => goal.donation_type.name)
                })
            }
        },

        created() {
            this.callApi()
            this.newExpense.event_id = this.pageId
            this.time = moment().format('YYYY-MM-DD');
        },

        components: {
            DatePicker,
            Multiselect
        }
    }
</script>

<style lang="scss">
    .create-expense {
        background-color: white;
        .multiselect__tags {
            padding-top: 20px;
            input {
                padding-left: 15px !important;
            }
        }
        .select-type {
            position: relative;
            label {
                position: absolute;
                z-index: 9;
                top: 6px;
                left: 24px;
                font-size: 11px;
            }
        }
        .btn-create-expense {
            width: 100%;
            text-align: center;
            button {
                margin-bottom: 0px;
            }
        }
        .daterangepicker {
            top: 786.013px !important;
        }
        .quill-editor {
            margin-bottom: 5px;
        }
    }
</style>
