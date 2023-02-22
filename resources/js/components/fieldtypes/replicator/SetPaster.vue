<template>

    <div class="replicator-set-picker">
        <button :class="{'btn-round': last, 'dropdown-icon': !last }" v-tooltip.right="__('Paste Set')" :aria-label="__('Paste Set')" @click="pasteSet()">
            <span class="icon icon-rocket text-grey-80 antialiased"></span>
        </button>
    </div>

</template>

<script>
export default {

    props: {
        index: Number,
        last: Boolean,
    },

    methods: {

        pasteSet() {
            this.$emit('pasted', this.index);
        }

    }

}
</script>
