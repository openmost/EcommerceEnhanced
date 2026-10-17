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
          :settings="section.settings"
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
}

interface SettingsSection {
  pluginName: string;
  title: string;
  settings: Setting[];
}

interface ManageSettingsState {
  sections: SettingsSection[];
  settingValues: Record<string, unknown>;
  isLoading: boolean;
  isSaving: boolean;
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
    };
  },
  created() {
    AjaxHelper.fetch<SettingsSection[]>({
      method: 'EcommerceEnhanced.getSiteSettings',
      idSite: this.idSite,
    }).then((sections: SettingsSection[]) => {
      const settingValues: Record<string, unknown> = {};
      sections.forEach((section: SettingsSection) => {
        section.settings.forEach((setting: Setting) => {
          settingValues[`${section.pluginName}.${setting.name}`] = setting.value;
        });
      });

      this.sections = sections;
      this.settingValues = settingValues;
    }).finally(() => {
      this.isLoading = false;
    });
  },
  methods: {
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
