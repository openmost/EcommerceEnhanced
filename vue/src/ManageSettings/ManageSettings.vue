<!--
  Matomo - free/libre analytics platform

  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="ecommerceEnhancedSettings">
    <ContentBlock :content-title="translate('EcommerceEnhanced_SettingsPageTitle')">
      <p>{{ translate('EcommerceEnhanced_SettingsPageIntro') }}</p>

      <ActivityIndicator :loading="isLoading" />

      <div
        v-for="section in sections"
        :key="section.pluginName"
        class="ecommerceEnhancedSettingsSection"
      >
        <h3 v-if="sections.length > 1">{{ sectionTitle(section.pluginName) }}</h3>
        <GroupedSettings
          :group-name="section.pluginName"
          :settings="settingsAround(section, true)"
          :all-setting-values="settingValues"
          @change="settingValues[`${section.pluginName}.${$event.name}`] = $event.value"
        />

        <div v-if="hasEventSteps(section)" class="ecommerceEnhancedEventSteps">
          <h3>{{ translate('EcommerceEnhanced_EventStepsTitle') }}</h3>
          <table class="entityTable">
            <thead>
              <tr>
                <th>{{ translate('Events_EventCategory') }}</th>
                <th>{{ translate('Events_EventAction') }}</th>
                <th>{{ translate('Events_EventName') }}</th>
                <th>{{ translate('EcommerceEnhanced_EventStepsTracked') }}</th>
                <th>{{ translate('EcommerceEnhanced_EventStepsNonInteraction') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(label, name) in eventStepLabels(section)" :key="name">
                <td>{{ EVENT_CATEGORY }}</td>
                <td>{{ label }}</td>
                <td><code>{{ name }}</code></td>
                <td>
                  <div class="switch">
                    <label>
                      <input
                        type="checkbox"
                        :checked="isStepIn('events_report_types', name)"
                        @change="onStepChange('events_report_types', name, $event)"
                      />
                      <span class="lever" />
                    </label>
                  </div>
                </td>
                <td>
                  <div class="switch">
                    <label>
                      <input
                        type="checkbox"
                        :checked="isStepIn('non_interaction_types', name)"
                        @change="onStepChange('non_interaction_types', name, $event)"
                      />
                      <span class="lever" />
                    </label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="form-help">{{ translate('EcommerceEnhanced_SettingEventsReportTypesHelp') }}</p>
          <p class="form-help">
            {{ translate('EcommerceEnhanced_SettingNonInteractionTypesHelp') }}
          </p>
        </div>

        <GroupedSettings
          :group-name="section.pluginName"
          :settings="settingsAround(section, false)"
          :all-setting-values="settingValues"
          @change="settingValues[`${section.pluginName}.${$event.name}`] = $event.value"
        />
      </div>

      <SaveButton
        v-if="!isLoading && sections.length"
        :saving="isSaving"
        @confirm="save()"
      />
    </ContentBlock>

    <ContentBlock
      v-if="funnels.available"
      :content-title="translate('EcommerceEnhanced_FunnelsTitle')"
    >
      <p>{{ translate('EcommerceEnhanced_FunnelsIntro') }}</p>
      <ul class="ecommerceEnhancedFunnels">
        <li
          v-for="funnel in funnels.funnels"
          :key="funnel.key"
          :class="`ecommerceEnhancedFunnel-${funnel.state}`"
        >
          <span :class="stateIcon(funnel.state)" />
          <strong>{{ funnel.name }}</strong>
          <span class="ecommerceEnhancedFunnelState">{{ stateLabel(funnel.state) }}</span>
        </li>
      </ul>
      <div class="ecommerceEnhancedFunnelsActions">
        <button
          class="btn"
          :disabled="isCreatingFunnels || !hasMissingFunnel"
          @click="createFunnels()"
        >{{ translate('EcommerceEnhanced_FunnelsCreate') }}</button>
        <button
          v-if="isSalesFunnelOutdated"
          class="btn"
          :disabled="isCreatingFunnels"
          @click="updateSalesFunnel()"
        >{{ translate('EcommerceEnhanced_FunnelsUpdateSales') }}</button>
      </div>
    </ContentBlock>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import {
  ActivityIndicator,
  AjaxHelper,
  ContentBlock,
  NotificationsStore,
  translate,
} from 'CoreHome';
import { GroupedSettings, SaveButton } from 'CorePluginsAdmin';

interface Setting {
  name: string;
  value: unknown;
  availableValues?: Record<string, string>;
}

// The two step lists of the free plugin, edited together as the switches of one table
const EVENTS_SETTING = 'events_report_types';
const NON_INTERACTION_SETTING = 'non_interaction_types';
const STEP_SETTINGS = [EVENTS_SETTING, NON_INTERACTION_SETTING];
const STEPS_PLUGIN = 'EcommerceEnhanced';

// The category of every step tracked as an event, EventType::EVENT_CATEGORY
const EVENT_CATEGORY = 'Ecommerce';

interface SettingsSection {
  pluginName: string;
  title: string;
  settings: Setting[];
}

interface FunnelStatus {
  key: string;
  name: string;
  state: 'missing' | 'exists' | 'outdated';
}

interface FunnelsSetup {
  available: boolean;
  funnels: FunnelStatus[];
}

interface ManageSettingsState {
  sections: SettingsSection[];
  settingValues: Record<string, unknown>;
  isLoading: boolean;
  isSaving: boolean;
  funnels: FunnelsSetup;
  isCreatingFunnels: boolean;
}

export default defineComponent({
  props: {
    idSite: {
      type: Number,
      required: true,
    },
  },
  components: {
    ActivityIndicator,
    ContentBlock,
    GroupedSettings,
    SaveButton,
  },
  data(): ManageSettingsState {
    return {
      sections: [],
      settingValues: {},
      isLoading: true,
      isSaving: false,
      funnels: { available: false, funnels: [] },
      isCreatingFunnels: false,
    };
  },
  setup() {
    return { EVENT_CATEGORY };
  },
  computed: {
    hasMissingFunnel(): boolean {
      return this.funnels.funnels.some((funnel: FunnelStatus) => funnel.state === 'missing');
    },
    isSalesFunnelOutdated(): boolean {
      return this.funnels.funnels.some((funnel: FunnelStatus) => funnel.key === 'sales' && funnel.state === 'outdated');
    },
  },
  created() {
    AjaxHelper.fetch<SettingsSection[]>({
      method: 'EcommerceEnhanced.getSiteSettings',
      idSite: this.idSite,
    }).then((sections: SettingsSection[]) => {
      const settingValues: Record<string, unknown> = {};
      sections.forEach((section: SettingsSection) => {
        section.settings.forEach((setting: Setting) => {
          // A value no longer offered, such as a retired step, would make Matomo refuse the save
          let { value } = setting;
          const available = setting.availableValues;
          if (Array.isArray(value) && available && !Array.isArray(available)) {
            value = value.filter(
              (entry: string) => Object.prototype.hasOwnProperty.call(available, entry),
            );
          }

          settingValues[`${section.pluginName}.${setting.name}`] = value;
        });
      });

      this.sections = sections;
      this.settingValues = settingValues;
    }).finally(() => {
      this.isLoading = false;
    });

    this.loadFunnels();
  },
  methods: {
    hasEventSteps(section: SettingsSection): boolean {
      return section.pluginName === STEPS_PLUGIN
        && section.settings.some((setting: Setting) => setting.name === EVENTS_SETTING);
    },
    // The settings before the step table (before is true) or after it, without the step lists
    settingsAround(section: SettingsSection, before: boolean): Setting[] {
      const position = section.settings.findIndex(
        (setting: Setting) => setting.name === EVENTS_SETTING,
      );
      if (position === -1) {
        return before ? section.settings : [];
      }

      return section.settings.filter((setting: Setting, index: number) => (
        !STEP_SETTINGS.includes(setting.name) && (before ? index < position : index > position)
      ));
    },
    eventStepLabels(section: SettingsSection): Record<string, string> {
      const setting = section.settings.find((s: Setting) => s.name === EVENTS_SETTING);
      return setting?.availableValues || {};
    },
    stepValues(settingName: string): string[] {
      const value = this.settingValues[`${STEPS_PLUGIN}.${settingName}`];
      return Array.isArray(value) ? value as string[] : [];
    },
    isStepIn(settingName: string, step: string): boolean {
      return this.stepValues(settingName).includes(step);
    },
    onStepChange(settingName: string, step: string, event: Event) {
      this.toggleStep(settingName, step, (event.target as HTMLInputElement).checked);
    },
    toggleStep(settingName: string, step: string, checked: boolean) {
      const values = this.stepValues(settingName).filter((value: string) => value !== step);
      if (checked) {
        values.push(step);
      }

      this.settingValues[`${STEPS_PLUGIN}.${settingName}`] = values;
    },
    loadFunnels() {
      AjaxHelper.fetch<FunnelsSetup>({
        method: 'EcommerceEnhanced.getFunnelsSetup',
        idSite: this.idSite,
      }).then((setup: FunnelsSetup) => {
        this.funnels = setup;
      });
    },
    createFunnels() {
      this.isCreatingFunnels = true;
      AjaxHelper.post(
        { method: 'EcommerceEnhanced.createFunnels', idSite: this.idSite },
        {},
      ).then(() => {
        const id = NotificationsStore.show({
          message: translate('EcommerceEnhanced_FunnelsCreated'),
          context: 'success',
          id: 'ecommerceEnhancedFunnels',
          type: 'transient',
        });
        NotificationsStore.scrollToNotification(id);
        this.loadFunnels();
      }).finally(() => {
        this.isCreatingFunnels = false;
      });
    },
    updateSalesFunnel() {
      this.isCreatingFunnels = true;
      AjaxHelper.post(
        { method: 'EcommerceEnhanced.updateSalesFunnel', idSite: this.idSite },
        {},
      ).then(() => {
        const id = NotificationsStore.show({
          message: translate('EcommerceEnhanced_FunnelsSalesUpdated'),
          context: 'success',
          id: 'ecommerceEnhancedFunnels',
          type: 'transient',
        });
        NotificationsStore.scrollToNotification(id);
        this.loadFunnels();
      }).finally(() => {
        this.isCreatingFunnels = false;
      });
    },
    stateIcon(state: FunnelStatus['state']): string {
      if (state === 'exists') {
        return 'icon-ok';
      }

      return state === 'outdated' ? 'icon-warning' : 'icon-minus';
    },
    stateLabel(state: FunnelStatus['state']): string {
      if (state === 'exists') {
        return translate('EcommerceEnhanced_FunnelExists');
      }

      return state === 'outdated'
        ? translate('EcommerceEnhanced_FunnelOutdated')
        : translate('EcommerceEnhanced_FunnelMissing');
    },
    sectionTitle(pluginName: string): string {
      return pluginName === 'EcommerceEnhanced'
        ? translate('EcommerceEnhanced_SettingsSectionFree')
        : translate('EcommerceEnhanced_SettingsSectionPremium');
    },
    save() {
      const settingValues: Record<string, Setting[]> = {};
      // Serialised like the website form does: booleans as 0 / 1, empty list entries dropped
      Object.entries(this.settingValues).forEach(([fullName, fieldValue]) => {
        const [pluginName, name] = fullName.split('.');
        let value = fieldValue;
        if (fieldValue === false) {
          value = '0';
        } else if (fieldValue === true) {
          value = '1';
        } else if (Array.isArray(fieldValue)) {
          value = fieldValue.filter((x) => !!x);
        }

        settingValues[pluginName] = settingValues[pluginName] || [];
        settingValues[pluginName].push({ name, value });
      });

      this.isSaving = true;
      AjaxHelper.post(
        { method: 'EcommerceEnhanced.updateSiteSettings', idSite: this.idSite },
        { settingValues },
      ).then(() => {
        const id = NotificationsStore.show({
          message: translate('EcommerceEnhanced_SettingsSaved'),
          context: 'success',
          id: 'ecommerceEnhancedSettings',
          type: 'transient',
        });
        NotificationsStore.scrollToNotification(id);
      }).finally(() => {
        this.isSaving = false;
      });
    },
  },
});
</script>
