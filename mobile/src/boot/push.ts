import { defineBoot } from '#q-app/wrappers';
import { initializeDeviceContext } from 'src/services/device-context';
import { setupPushListeners } from 'src/services/push-manager';

export default defineBoot(({ router }) => {
  void initializeDeviceContext();
  void setupPushListeners(router);
});
